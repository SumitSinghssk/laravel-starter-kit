<?php

namespace App\Services\Transfer\Types;

use App\Models\BlogCategory;
use App\Services\Transfer\RemoteImage;
use App\Services\Transfer\TransferType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlogCategoryTransfer extends TransferType
{
    public function key(): string
    {
        return 'blog-categories';
    }

    public function label(): string
    {
        return 'Blog categories';
    }

    public function singular(): string
    {
        return 'category';
    }

    public function viewPermission(): string
    {
        return 'admin.blog-categories.view';
    }

    public function importPermission(): ?string
    {
        return 'admin.blog-categories.create';
    }

    public function listUrl(): string
    {
        return route('admin.blog-categories.index');
    }

    public function columns(): array
    {
        return [
            'name' => ['Category name', true, 'Guides'],
            'slug' => ['Web address. Made from the name when empty', false, 'guides'],
            'parent' => ['Slug or name of the parent category (top-level only), empty for a top-level one', false, 'resources'],
            'description' => ['Short description', false, 'Step-by-step articles.'],
            'status' => ['active or inactive (default active)', false, 'active'],
            'image' => ['Image URL (downloaded on import)', false, 'https://example.com/guides.jpg'],
        ];
    }

    public function exportQuery(): Builder
    {
        return BlogCategory::query()->with('parent:id,slug')->orderByRaw('parent_id IS NOT NULL')->orderBy('name');
    }

    public function exportRow(Model $category): array
    {
        return [
            $category->name,
            $category->slug,
            $category->parent?->slug,
            $category->description,
            $category->status?->value,
            self::fileUrl($category->image),
        ];
    }

    public function normalize(array $row): array
    {
        return [
            ...$row,
            'slug' => self::slug($row['slug'] ?? '', $row['name'] ?? ''),
            'parent' => trim((string) ($row['parent'] ?? '')),
            'status' => self::status($row['status'] ?? ''),
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'image' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function identity(array $row): string
    {
        return $row['slug'];
    }

    public function existing(array $row): ?string
    {
        return BlogCategory::where('slug', $row['slug'])->exists() ? "A category with the address “{$row['slug']}” already exists." : null;
    }

    public function check(array $row, array $earlier): array
    {
        if ($row['parent'] === '') {
            return ['errors' => [], 'warnings' => []];
        }

        $parentSlug = Str::slug($row['parent']);

        if ($parentSlug === $row['slug']) {
            return ['errors' => ['A category cannot be its own parent.'], 'warnings' => []];
        }

        $parent = $this->findParent($row['parent']);

        if ($parent) {
            return ['errors' => $parent->parent_id ? ["“{$row['parent']}” is a sub-category; categories are only two levels deep."] : [], 'warnings' => []];
        }

        return in_array($parentSlug, $earlier, true)
            ? ['errors' => [], 'warnings' => []]
            : ['errors' => ["Parent category “{$row['parent']}” doesn't exist (and isn't above this row in the file)."], 'warnings' => []];
    }

    public function rowLabel(array $row): string
    {
        return (string) ($row['name'] ?? '');
    }

    public function create(array $row, RemoteImage $images, Authenticatable $user): Model
    {
        $parent = $row['parent'] !== '' ? $this->findParent($row['parent']) : null;

        if ($row['parent'] !== '' && (! $parent || $parent->parent_id)) {
            throw new \RuntimeException("Parent category “{$row['parent']}” isn't available.");
        }

        return BlogCategory::create([
            'name' => $row['name'],
            'slug' => $row['slug'],
            'parent_id' => $parent?->id,
            'description' => $row['description'] ?: null,
            'status' => $row['status'],
            'image' => filled($row['image'] ?? null) ? $images->store($row['image'], 'blog-categories') : null,
        ]);
    }

    public function editUrl(Model $model): ?string
    {
        return route('admin.blog-categories.edit', $model);
    }

    private function findParent(string $reference): ?BlogCategory
    {
        return BlogCategory::where('slug', Str::slug($reference))->orWhere('name', $reference)->first();
    }
}
