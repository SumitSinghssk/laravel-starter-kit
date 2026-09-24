<?php

namespace App\Services\Transfer;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Csv
{
    private const FORMULA_START = ['=', '+', '-', '@', "\t", "\r"];

    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($value) => self::cell($value), $row), ',', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public static function read(string $path, int $maxRows = 5000): array
    {
        $handle = fopen($path, 'r');
        $first = (string) fgets($handle);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        $headers = array_map(fn ($h) => strtolower(trim((string) $h)), str_getcsv(trim($first, "\r\n"), $delimiter, '"', ''));

        if (! array_filter($headers)) {
            throw ValidationException::withMessages(['file' => 'The first line of the file must be the column names.']);
        }

        $rows = [];
        $line = 1;

        while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $line++;

            if ($cells === [null] || ! array_filter($cells, fn ($c) => trim((string) $c) !== '')) {
                continue;
            }

            if (count($rows) >= $maxRows) {
                fclose($handle);
                throw ValidationException::withMessages(['file' => "The file has more than {$maxRows} rows. Split it into smaller files."]);
            }

            $values = [];
            foreach ($headers as $i => $header) {
                if ($header !== '') {
                    $values[$header] = self::uncell((string) ($cells[$i] ?? ''));
                }
            }

            $rows[] = [$line, $values];
        }

        fclose($handle);

        return ['headers' => array_values(array_filter($headers)), 'rows' => $rows];
    }

    private static function cell(mixed $value): string
    {
        $value = match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'yes' : 'no',
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i'),
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };

        return $value !== '' && in_array($value[0], self::FORMULA_START, true) ? "'".$value : $value;
    }

    private static function uncell(string $value): string
    {
        $value = trim($value);

        return strlen($value) > 1 && $value[0] === "'" && in_array($value[1], self::FORMULA_START, true) ? substr($value, 1) : $value;
    }
}
