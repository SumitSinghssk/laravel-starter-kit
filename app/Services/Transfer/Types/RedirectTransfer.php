<?php

namespace App\Services\Transfer\Types;

use App\Models\Redirect;
use App\Services\Transfer\RemoteImage;
use App\Services\Transfer\TransferType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RedirectTransfer extends TransferType
{
    public function key(): string
    {
        return 'redirects';
    }

    public function label(): string
    {
        return 'Redirects';
    }

    public function singular(): string
    {
        return 'redirect';
    }

    public function viewPermission(): string
    {
        return 'admin.redirects.view';
    }

    public function importPermission(): ?string
    {
        return 'admin.redirects.create';
    }

    public function listUrl(): string
    {
        return route('admin.redirects.index');
    }

    public function columns(): array
    {
        return [
            'old_url' => ['Path on this site that should redirect', true, '/old-page'],
            'new_url' => ['Path on this site or full URL to send visitors to', true, '/new-page'],
            'type' => ['301 (permanent, default), 302, 307 or 308', false, '301'],
            'status' => ['active or inactive (default active)', false, 'active'],
            'note' => ['Why it exists (optional)', false, 'Renamed in 2026'],
        ];
    }

    public function exportQuery(): Builder
    {
        return Redirect::query()->orderBy('source_path');
    }

    public function exportRow(Model $r): array
    {
        return [$r->source_path, $r->target_url, $r->status_code, $r->status?->value, $r->note];
    }

    public function normalize(array $row): array
    {
        $target = trim((string) ($row['new_url'] ?? ''));

        return [
            ...$row,
            'old_url' => trim((string) ($row['old_url'] ?? '')) === '' ? '' : Redirect::normalizePath($row['old_url']),
            'new_url' => $target === '' || preg_match('#^[a-z][a-z0-9+.-]*:|^//#i', $target) ? $target : '/'.ltrim($target, '/'),
            'type' => trim((string) ($row['type'] ?? '')) ?: '301',
            'status' => self::status($row['status'] ?? ''),
        ];
    }

    public function rules(): array
    {
        return [
            'old_url' => ['required', 'string', 'max:500', 'not_in:/', 'not_regex:#^/admin(/|$)#'],
            'new_url' => ['required', 'string', 'max:2048', 'regex:#^(/(?!/)|https?://[^\s/]+)#i'],
            'type' => ['required', 'in:'.implode(',', array_keys(Redirect::STATUS_CODES))],
            'status' => ['required', 'in:active,inactive'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function identity(array $row): string
    {
        return $row['old_url'];
    }

    public function existing(array $row): ?string
    {
        return Redirect::where('source_path', $row['old_url'])->exists() ? "{$row['old_url']} already has a redirect." : null;
    }

    public function check(array $row, array $earlier): array
    {
        $loop = Redirect::loopChain($row['old_url'], $row['new_url']);

        return ['errors' => $loop ? ['This would create a redirect loop: '.implode(' → ', $loop).'.'] : [], 'warnings' => []];
    }

    public function rowLabel(array $row): string
    {
        return ($row['old_url'] ?? '').' → '.($row['new_url'] ?? '');
    }

    public function create(array $row, RemoteImage $images, Authenticatable $user): Model
    {
        if ($loop = Redirect::loopChain($row['old_url'], $row['new_url'])) {
            throw new \RuntimeException('This would create a redirect loop: '.implode(' → ', $loop).'.');
        }

        return Redirect::create([
            'source_path' => $row['old_url'],
            'target_url' => $row['new_url'],
            'status_code' => (int) $row['type'],
            'status' => $row['status'],
            'note' => $row['note'] ?: null,
        ]);
    }

    public function editUrl(Model $model): ?string
    {
        return route('admin.redirects.edit', $model);
    }
}
