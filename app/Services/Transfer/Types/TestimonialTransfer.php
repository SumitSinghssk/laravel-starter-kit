<?php

namespace App\Services\Transfer\Types;

use App\Models\Testimonial;
use App\Services\Transfer\RemoteImage;
use App\Services\Transfer\TransferType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TestimonialTransfer extends TransferType
{
    public function key(): string
    {
        return 'testimonials';
    }

    public function label(): string
    {
        return 'Testimonials';
    }

    public function singular(): string
    {
        return 'testimonial';
    }

    public function viewPermission(): string
    {
        return 'admin.testimonials.view';
    }

    public function importPermission(): ?string
    {
        return 'admin.testimonials.create';
    }

    public function listUrl(): string
    {
        return route('admin.testimonials.index');
    }

    public function columns(): array
    {
        return [
            'name' => ['Client name', true, 'Priya Nair'],
            'designation' => ['Their role', false, 'Head of Product'],
            'company' => ['Their company', false, 'Finlytic'],
            'quote' => ['What they said (10–2000 characters)', true, 'Great to work with, fast and reliable.'],
            'rating' => ['1–5 stars, or empty', false, '5'],
            'featured' => ['yes or no (default no)', false, 'no'],
            'sort_order' => ['Number, lower comes first (default 0)', false, '0'],
            'status' => ['active or inactive (default active)', false, 'active'],
            'photo' => ['Photo URL (downloaded on import)', false, ''],
        ];
    }

    public function exportQuery(): Builder
    {
        return Testimonial::query()->ordered();
    }

    public function exportRow(Model $t): array
    {
        return [$t->name, $t->designation, $t->company, $t->quote, $t->rating, $t->is_featured, $t->sort_order, $t->status?->value, self::fileUrl($t->photo)];
    }

    public function normalize(array $row): array
    {
        return [
            ...$row,
            'rating' => trim((string) ($row['rating'] ?? '')) === '' ? null : trim($row['rating']),
            'featured' => self::bool($row['featured'] ?? ''),
            'sort_order' => trim((string) ($row['sort_order'] ?? '')) === '' ? 0 : trim($row['sort_order']),
            'status' => self::status($row['status'] ?? ''),
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'quote' => ['required', 'string', 'min:10', 'max:2000'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'sort_order' => ['integer', 'min:0', 'max:65535'],
            'status' => ['required', 'in:active,inactive'],
            'photo' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function identity(array $row): string
    {
        return Str::lower(trim($row['name'])).'|'.Str::lower(preg_replace('/\s+/', ' ', trim($row['quote'])));
    }

    public function existing(array $row): ?string
    {
        $exists = Testimonial::whereRaw('LOWER(name) = ?', [Str::lower(trim($row['name']))])->get(['quote'])
            ->contains(fn ($t) => Str::lower(preg_replace('/\s+/', ' ', trim($t->quote))) === Str::lower(preg_replace('/\s+/', ' ', trim($row['quote']))));

        return $exists ? "A testimonial from {$row['name']} with the same words already exists." : null;
    }

    public function rowLabel(array $row): string
    {
        return (string) ($row['name'] ?? '');
    }

    public function create(array $row, RemoteImage $images, Authenticatable $user): Model
    {
        return Testimonial::create([
            'name' => $row['name'],
            'designation' => $row['designation'] ?: null,
            'company' => $row['company'] ?: null,
            'quote' => $row['quote'],
            'rating' => $row['rating'],
            'is_featured' => $row['featured'],
            'sort_order' => (int) $row['sort_order'],
            'status' => $row['status'],
            'photo' => filled($row['photo'] ?? null) ? $images->store($row['photo'], 'testimonials') : null,
        ]);
    }

    public function editUrl(Model $model): ?string
    {
        return route('admin.testimonials.edit', $model);
    }
}
