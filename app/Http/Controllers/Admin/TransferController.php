<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Import;
use App\Services\Transfer\Csv;
use App\Services\Transfer\ImportService;
use App\Services\Transfer\TransferRegistry;
use App\Services\Transfer\TransferType;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class TransferController extends Controller
{
    public function __construct(private TransferRegistry $registry, private ImportService $imports) {}

    public function export(string $type)
    {
        $type = $this->registry->find($type);
        Gate::authorize($type->viewPermission());

        $columns = array_keys($type->columns());
        $query = $type->exportQuery();

        $rows = (function () use ($query, $type) {
            foreach ($query->lazy(500) as $model) {
                yield $type->exportRow($model);
            }
        })();

        return Csv::download($type->key().'-'.now()->format('Y-m-d-His').'.csv', $columns, $rows);
    }

    public function template(string $type)
    {
        $type = $this->importable($type);

        return Csv::download($type->key().'-import-template.csv', array_keys($type->columns()), [array_column($type->columns(), 2)]);
    }

    public function create(string $type)
    {
        $type = $this->importable($type);

        return view('admin.transfer.create', [
            'type' => $type,
            'recent' => Import::where('user_id', auth()->id())->where('type', $type->key())->latest()->take(5)->get(),
        ]);
    }

    public function store(Request $request, string $type)
    {
        $type = $this->importable($type);

        $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:csv,txt']], [
            'file.mimes' => 'Upload a .csv file (in Excel: File → Save as → CSV UTF-8).',
            'file.max' => 'The file can be at most 10 MB.',
        ]);

        $import = $this->imports->analyse($type, $request->file('file'), $request->user());

        return to_route('admin.transfer.imports.show', $import);
    }

    public function show(Request $request, Import $import)
    {
        $type = $this->authorizeImport($request, $import);

        $filter = in_array($request->show, ['new', 'exists', 'duplicate', 'invalid', 'created', 'failed', 'warnings'], true) ? $request->show : null;
        $rows = collect($import->rows())
            ->when($filter === 'warnings', fn ($rows) => $rows->filter(fn ($row) => $row['warnings']))
            ->when($filter && $filter !== 'warnings', fn ($rows) => $rows->where('status', $filter))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginated = new LengthAwarePaginator($rows->forPage($page, 50)->values(), $rows->count(), 50, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('admin.transfer.show', ['import' => $import, 'type' => $type, 'rows' => $paginated, 'filter' => $filter]);
    }

    public function step(Request $request, Import $import)
    {
        $type = $this->authorizeImport($request, $import);
        $import = $this->imports->step($import, $type, $request->user());

        return response()->json([
            'status' => $import->status,
            'percent' => $import->percent(),
            'created' => $import->tally('created'),
            'failed' => $import->tally('failed'),
            'skipped' => $import->tally('exists') + $import->tally('duplicate'),
        ]);
    }

    public function destroy(Request $request, Import $import)
    {
        $type = $this->authorizeImport($request, $import);
        $import->delete();

        return to_route('admin.transfer.create', $type->key())->with('success', 'Import discarded. Nothing was imported from it.');
    }

    private function importable(string $key): TransferType
    {
        $type = $this->registry->find($key);
        abort_unless($type->importPermission(), 404);
        Gate::authorize($type->importPermission());

        return $type;
    }

    private function authorizeImport(Request $request, Import $import): TransferType
    {
        abort_unless((int) $import->user_id === (int) $request->user()->id, 404);

        return $this->importable($import->type);
    }
}
