<?php

namespace App\Services\Transfer\Types;

use App\Models\Seo;
use App\Services\Transfer\RemoteImage;
use App\Services\Transfer\TransferType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SeoTransfer extends TransferType
{
    public function key(): string
    {
        return 'seo';
    }

    public function label(): string
    {
        return 'SEO records';
    }

    public function singular(): string
    {
        return 'SEO record';
    }

    public function viewPermission(): string
    {
        return 'admin.seo.view';
    }

    public function importPermission(): ?string
    {
        return 'admin.seo.create';
    }

    public function listUrl(): string
    {
        return route('admin.seo.index');
    }

    public function columns(): array
    {
        return [
            'page' => ['Page name', true, 'About us'],
            'slug' => ['URL path of the page ("/" for home)', true, 'about'],
            'meta_title' => ['Title in search results', false, 'About us · My Company'],
            'meta_description' => ['Snippet in search results', false, 'Who we are and what we do.'],
            'og_image' => ['Social share image URL (downloaded on import)', false, ''],
            'index' => ['yes = search engines may index it (default yes)', false, 'yes'],
            'schema' => ['JSON-LD structured data', false, ''],
            'faqs' => ['FAQs as JSON: [{"question":"…","answer":"…"}]', false, ''],
            'header_scripts' => ['Scripts for <head>', false, ''],
            'footer_scripts' => ['Scripts before </body>', false, ''],
            'custom_css' => ['Page CSS', false, ''],
        ];
    }

    public function exportQuery(): Builder
    {
        return Seo::query()->orderBy('slug');
    }

    public function exportRow(Model $s): array
    {
        return [$s->page, $s->slug, $s->meta_title, $s->meta_description, self::fileUrl($s->og_image), $s->index, $s->schema, $s->faqs ?: '', $s->header_scripts, $s->footer_scripts, $s->custom_css];
    }

    public function normalize(array $row): array
    {
        $slug = trim((string) ($row['slug'] ?? ''));

        return [
            ...$row,
            'slug' => $slug === '/' ? '/' : trim($slug, '/'),
            'index' => self::bool($row['index'] ?? '', true),
        ];
    }

    public function rules(): array
    {
        return [
            'page' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'og_image' => ['nullable', 'string', 'max:2000'],
            'schema' => ['nullable', 'string'],
            'faqs' => ['nullable', 'json'],
            'header_scripts' => ['nullable', 'string'],
            'footer_scripts' => ['nullable', 'string'],
            'custom_css' => ['nullable', 'string'],
        ];
    }

    public function identity(array $row): string
    {
        return $row['slug'];
    }

    public function existing(array $row): ?string
    {
        return Seo::where('slug', $row['slug'])->exists() ? "SEO settings for “{$row['slug']}” already exist." : null;
    }

    public function rowLabel(array $row): string
    {
        return ($row['page'] ?? '').' ('.($row['slug'] ?? '').')';
    }

    public function create(array $row, RemoteImage $images, Authenticatable $user): Model
    {
        return Seo::create([
            'page' => $row['page'],
            'slug' => $row['slug'],
            'meta_title' => $row['meta_title'] ?: null,
            'meta_description' => $row['meta_description'] ?: null,
            'og_image' => filled($row['og_image'] ?? null) ? $images->store($row['og_image'], 'seo') : null,
            'index' => $row['index'],
            'schema' => $row['schema'] ?: null,
            'faqs' => filled($row['faqs'] ?? null) ? json_decode($row['faqs'], true) : null,
            'header_scripts' => $row['header_scripts'] ?: null,
            'footer_scripts' => $row['footer_scripts'] ?: null,
            'custom_css' => $row['custom_css'] ?: null,
        ]);
    }

    public function editUrl(Model $model): ?string
    {
        return route('admin.seo.edit', $model);
    }
}
