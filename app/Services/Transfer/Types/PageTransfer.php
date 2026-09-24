<?php

namespace App\Services\Transfer\Types;

use App\Models\Page;
use App\Services\Transfer\RemoteImage;
use App\Services\Transfer\TransferType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PageTransfer extends TransferType
{
    public function key(): string
    {
        return 'pages';
    }

    public function label(): string
    {
        return 'Pages';
    }

    public function singular(): string
    {
        return 'page';
    }

    public function viewPermission(): string
    {
        return 'admin.pages.view';
    }

    public function importPermission(): ?string
    {
        return 'admin.pages.create';
    }

    public function listUrl(): string
    {
        return route('admin.pages.index');
    }

    public function columns(): array
    {
        return [
            'title' => ['Page title', true, 'Privacy policy'],
            'slug' => ['Web address. Made from the title when empty', false, 'privacy-policy'],
            'content' => ['The page, as HTML or plain text', true, '<p>We respect your privacy…</p>'],
            'status' => ['active or inactive (default active)', false, 'active'],
            'published_at' => ['Publish date, e.g. 2026-09-23 10:00 (empty = now)', false, ''],
            'featured_image' => ['Banner image URL (downloaded on import)', false, ''],
        ];
    }

    public function exportQuery(): Builder
    {
        return Page::query()->latest('id');
    }

    public function exportRow(Model $page): array
    {
        return [$page->title, $page->slug, $page->content, $page->status?->value, $page->published_at, self::fileUrl($page->featured_image)];
    }

    public function normalize(array $row): array
    {
        return [
            ...$row,
            'slug' => self::slug($row['slug'] ?? '', $row['title'] ?? ''),
            'status' => self::status($row['status'] ?? ''),
            'published_at' => self::date($row['published_at'] ?? ''),
        ];
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'published_at' => ['nullable', 'date'],
            'featured_image' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function identity(array $row): string
    {
        return $row['slug'];
    }

    public function existing(array $row): ?string
    {
        return Page::where('slug', $row['slug'])->exists() ? "A page with the address “{$row['slug']}” already exists." : null;
    }

    public function rowLabel(array $row): string
    {
        return (string) ($row['title'] ?? '');
    }

    public function create(array $row, RemoteImage $images, Authenticatable $user): Model
    {
        return Page::create([
            'user_id' => $user->getAuthIdentifier(),
            'title' => $row['title'],
            'slug' => $row['slug'],
            'content' => $row['content'],
            'status' => $row['status'],
            'published_at' => $row['published_at'] ?? now(),
            'featured_image' => filled($row['featured_image'] ?? null) ? $images->store($row['featured_image'], 'pages') : null,
        ]);
    }

    public function editUrl(Model $model): ?string
    {
        return route('admin.pages.edit', $model);
    }
}
