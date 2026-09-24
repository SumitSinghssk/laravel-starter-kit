<?php

namespace App\Services\Transfer;

use App\Models\Import;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportService
{
    public const MAX_ROWS = 5000;

    public function __construct(private RemoteImage $images) {}

    public function analyse(TransferType $type, UploadedFile $file, User $user): Import
    {
        Import::where('user_id', $user->id)->where('created_at', '<', now()->subWeek())->get()->each->delete();

        $csv = Csv::read($file->getRealPath(), self::MAX_ROWS);
        $columns = $type->columns();

        $missing = array_keys(array_filter($columns, fn ($column, $name) => $column[1] && ! in_array($name, $csv['headers'], true), ARRAY_FILTER_USE_BOTH));
        if ($missing) {
            throw ValidationException::withMessages(['file' => 'The file is missing the required '.(count($missing) === 1 ? 'column' : 'columns').': '.implode(', ', $missing).'. Download the template to see the expected columns.']);
        }

        if (! $csv['rows']) {
            throw ValidationException::withMessages(['file' => 'The file has column names but no rows.']);
        }

        $unknown = array_values(array_diff($csv['headers'], array_keys($columns)));
        $seen = [];
        $earlier = [];
        $rows = [];

        foreach ($csv['rows'] as [$line, $values]) {
            $data = $type->normalize(array_intersect_key($values, $columns) + array_fill_keys(array_keys($columns), ''));
            $row = ['line' => $line, 'label' => '', 'status' => 'new', 'messages' => [], 'warnings' => [], 'data' => $data];

            $validator = Validator::make($data, $type->rules(), [], array_combine(array_keys($columns), array_map(fn ($name) => str_replace('_', ' ', $name), array_keys($columns))));

            if ($validator->fails()) {
                $row['status'] = 'invalid';
                $row['messages'] = $validator->errors()->all();
                $row['label'] = $type->rowLabel($data);
                $rows[] = $row;

                continue;
            }

            $row['label'] = $type->rowLabel($data);
            $identity = $type->identity($data);

            if ($identity !== '' && isset($seen[$identity])) {
                $row['status'] = 'duplicate';
                $row['messages'] = ["Same as row {$seen[$identity]} of this file; only the first one is imported."];
            } elseif ($reason = $type->existing($data)) {
                $row['status'] = 'exists';
                $row['messages'] = [$reason.' It will not be imported.'];
            } else {
                $check = $type->check($data, $earlier);
                $row['warnings'] = $check['warnings'];

                if ($check['errors']) {
                    $row['status'] = 'invalid';
                    $row['messages'] = $check['errors'];
                } else {
                    $earlier[] = $identity;
                }
            }

            if ($identity !== '') {
                $seen[$identity] ??= $line;
            }

            $rows[] = $row;
        }

        $import = Import::create([
            'type' => $type->key(),
            'user_id' => $user->id,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'status' => Import::READY,
            'total' => count($rows),
            'counts' => [...$this->count($rows), 'unknown_columns' => $unknown],
        ]);

        $import->saveRows($rows);

        return $import;
    }

    public function step(Import $import, TransferType $type, User $user, int $seconds = 8): Import
    {
        $lock = Cache::lock('import-step:'.$import->id, $seconds + 120);

        if (! $lock->get()) {
            return $import->refresh();
        }

        try {
            $import->refresh();

            if ($import->status === Import::COMPLETED) {
                return $import;
            }

            @set_time_limit($seconds + 120);
            $deadline = microtime(true) + $seconds;
            $rows = $import->rows();
            $index = $import->cursor;

            $import->status = Import::RUNNING;

            for (; $index < count($rows) && microtime(true) < $deadline; $index++) {
                if ($rows[$index]['status'] !== 'new') {
                    continue;
                }

                $rows[$index] = $this->importRow($rows[$index], $type, $user);
            }

            $import->forceFill([
                'cursor' => $index,
                'counts' => [...$this->count($rows), 'unknown_columns' => $import->counts['unknown_columns'] ?? []],
                'status' => $index >= count($rows) ? Import::COMPLETED : Import::RUNNING,
                'finished_at' => $index >= count($rows) ? now() : null,
            ])->save();

            $import->saveRows($rows);
        } finally {
            $lock->release();
        }

        return $import;
    }

    private function importRow(array $row, TransferType $type, User $user): array
    {
        if ($reason = $type->existing($row['data'])) {
            return [...$row, 'status' => 'exists', 'messages' => [$reason.' Skipped.']];
        }

        try {
            $model = DB::transaction(fn () => $type->create($row['data'], $this->images, $user));
            $this->images->commit();

            return [...$row, 'status' => 'created', 'messages' => [], 'result' => ['id' => $model->getKey(), 'url' => $type->editUrl($model)]];
        } catch (Throwable $e) {
            $this->images->rollback();

            if (! $e instanceof \RuntimeException) {
                report($e);
            }

            return [...$row, 'status' => 'failed', 'messages' => [$e instanceof \RuntimeException ? $e->getMessage() : 'Could not be saved: '.class_basename($e).'.']];
        }
    }

    private function count(array $rows): array
    {
        $counts = array_fill_keys(['new', 'exists', 'duplicate', 'invalid', 'created', 'failed'], 0);

        foreach ($rows as $row) {
            $counts[$row['status']]++;
        }

        return $counts;
    }
}
