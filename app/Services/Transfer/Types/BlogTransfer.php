<?php

namespace App\Services\Transfer\Types;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\User;
use App\Services\Transfer\RemoteImage;
use App\Services\Transfer\TransferType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlogTransfer extends TransferType
{
    public function key(): string
    {
        return 'blogs';
    }

    public function label(): string
    {
        return 'Blog posts';
    }

    public function singular(): string
    {
        return 'blog post';
    }

    public function viewPermission(): string
    {
        return 'admin.blogs.view';
    }

    public function importPermission(): ?string
    {
        return 'admin.blogs.create';
    }

    public function listUrl(): string
    {
        return route('admin.blogs.index');
    }

    public function columns(): array
    {
        return [
            'title' => ['Post title', true, 'Ten tips for faster websites'],
            'slug' => ['Web address. Made from the title when empty', false, 'faster-websites'],
            'excerpt' => ['Short summary (max 500 characters)', false, 'What slows a site down, and how to fix it.'],
            'content' => ['The post, as HTML or plain text', true, '<p>First paragraph…</p>'],
            'status' => ['active or inactive (default active)', false, 'active'],
            'published_at' => ['Publish date, e.g. 2026-09-23 10:00 (empty = now)', false, '2026-09-23 10:00'],
            'categories' => ['Category names or slugs, separated by |', false, 'Performance | Guides'],
            'featured_image' => ['Image URL (downloaded on import)', false, 'https://example.com/cover.jpg'],
            'author_email' => ['Email of an existing admin user (default: you)', false, ''],
        ];
    }

    public function exportQuery(): Builder
    {
        return Blog::query()->with(['categories:id,name', 'author:id,email'])->latest('id');
    }

    public function exportRow(Model $blog): array
    {
        return [
            $blog->title,
            $blog->slug,
            $blog->excerpt,
            $blog->content,
            $blog->status?->value,
            $blog->published_at,
            $blog->categories->pluck('name')->implode(' | '),
            self::fileUrl($blog->featured_image),
            $blog->author?->email,
        ];
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
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'published_at' => ['nullable', 'date'],
            'featured_image' => ['nullable', 'string', 'max:2000'],
            'author_email' => ['nullable', 'email'],
        ];
    }

    public function identity(array $row): string
    {
        return $row['slug'];
    }

    public function existing(array $row): ?string
    {
        return Blog::where('slug', $row['slug'])->exists() ? "A blog post with the address “{$row['slug']}” already exists." : null;
    }

    public function check(array $row, array $earlier): array
    {
        $warnings = [];
        $errors = [];

        foreach ($this->categoryNames($row) as $name) {
            if (! $this->findCategory($name)) {
                $warnings[] = "Category “{$name}” doesn't exist and will be left out.";
            }
        }

        if (filled($row['author_email'] ?? null) && ! User::where('email', $row['author_email'])->exists()) {
            $errors[] = "No admin user has the email {$row['author_email']}.";
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    public function rowLabel(array $row): string
    {
        return (string) ($row['title'] ?? '');
    }

    public function create(array $row, RemoteImage $images, Authenticatable $user): Model
    {
        $author = filled($row['author_email'] ?? null) ? User::where('email', $row['author_email'])->first() : null;

        $blog = Blog::create([
            'user_id' => $author?->id ?? $user->getAuthIdentifier(),
            'title' => $row['title'],
            'slug' => $row['slug'],
            'excerpt' => $row['excerpt'] ?: null,
            'content' => $row['content'],
            'status' => $row['status'],
            'published_at' => $row['published_at'] ?? now(),
            'featured_image' => filled($row['featured_image'] ?? null) ? $images->store($row['featured_image'], 'blogs') : null,
        ]);

        $ids = collect($this->categoryNames($row))->map(fn ($name) => $this->findCategory($name)?->id)->filter()->unique();
        $blog->categories()->sync($ids);

        return $blog;
    }

    public function editUrl(Model $model): ?string
    {
        return route('admin.blogs.edit', $model);
    }

    private function categoryNames(array $row): array
    {
        return collect(explode('|', (string) ($row['categories'] ?? '')))->map(fn ($name) => trim($name))->filter()->values()->all();
    }

    private function findCategory(string $name): ?BlogCategory
    {
        return BlogCategory::where('slug', Str::slug($name))->orWhere('name', $name)->first();
    }
}
