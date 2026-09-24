<?php

namespace App\Services\Transfer;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

abstract class TransferType
{
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function singular(): string;

    abstract public function viewPermission(): string;

    public function importPermission(): ?string
    {
        return null;
    }

    abstract public function listUrl(): string;

    abstract public function columns(): array;

    abstract public function exportQuery(): Builder;

    abstract public function exportRow(Model $model): array;

    public function canImport(Authenticatable $user): bool
    {
        return $this->importPermission() !== null && $user->can($this->importPermission());
    }

    public function normalize(array $row): array
    {
        return $row;
    }

    public function rules(): array
    {
        return [];
    }

    public function identity(array $row): string
    {
        return '';
    }

    public function existing(array $row): ?string
    {
        return null;
    }

    public function check(array $row, array $earlier): array
    {
        return ['errors' => [], 'warnings' => []];
    }

    public function rowLabel(array $row): string
    {
        return '';
    }

    public function create(array $row, RemoteImage $images, Authenticatable $user): Model
    {
        throw new \LogicException(static::class.' does not import.');
    }

    public function editUrl(Model $model): ?string
    {
        return null;
    }

    protected static function bool(mixed $value, bool $default = false): bool
    {
        $value = Str::lower(trim((string) $value));

        return $value === '' ? $default : in_array($value, ['1', 'yes', 'y', 'true', 'on', 'active', 'published', 'enabled'], true);
    }

    protected static function status(mixed $value, string $default = 'active'): string
    {
        $value = Str::lower(trim((string) $value));

        return match (true) {
            $value === '' => $default,
            in_array($value, ['active', 'yes', '1', 'true', 'published', 'live', 'on', 'enabled'], true) => 'active',
            in_array($value, ['inactive', 'no', '0', 'false', 'draft', 'hidden', 'off', 'disabled'], true) => 'inactive',
            default => $value,
        };
    }

    protected static function date(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return rescue(fn () => Carbon::parse(str_replace('/', '-', $value))->format('Y-m-d H:i:s'), $value, report: false);
    }

    protected static function slug(mixed $value, mixed $fallback = ''): string
    {
        return Str::slug(trim((string) $value) !== '' ? (string) $value : (string) $fallback);
    }

    protected static function fileUrl(?string $path): string
    {
        return $path ? url('storage/'.ltrim($path, '/')) : '';
    }
}
