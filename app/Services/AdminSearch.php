<?php

namespace App\Services;

use App\Enums\EnquiryStatus;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Enquiry;
use App\Models\Gallery;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Seo;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdminSearch
{
    public const PER_GROUP = 5;

    public const MIN_LENGTH = 2;

    public function search(User $user, string $query): array
    {
        $query = Str::of($query)->squish()->limit(100, '')->toString();

        if (mb_strlen($query) < self::MIN_LENGTH) {
            return [];
        }

        return collect($this->sources())
            ->filter(fn (array $source) => $user->can($source['permission']))
            ->map(fn (array $source, string $key) => $this->run($key, $source, $user, $query))
            ->filter(fn (array $group) => $group['total'] > 0)
            ->values()
            ->all();
    }

    private function run(string $key, array $source, User $user, string $query): array
    {
        $builder = $source['query']($this->terms($query), $query);
        $records = (clone $builder)->limit(self::PER_GROUP + 1)->get();
        $total = $records->count() > self::PER_GROUP ? $builder->count() : $records->count();
        $records = $records->take(self::PER_GROUP);

        return [
            'key' => $key,
            'label' => $source['label'],
            'icon' => $source['icon'],
            'total' => $total,
            'more' => $total > self::PER_GROUP && $source['index'] ? $source['index']($query) : null,
            'items' => $records->map(fn (Model $record) => ['id' => $key.':'.$record->getKey(), ...$source['item']($record, $user)])->all(),
        ];
    }

    private function sources(): array
    {
        return [
            'enquiries' => [
                'label' => 'Enquiries',
                'icon' => 'inbox',
                'permission' => 'admin.enquiries.view',
                'query' => function (array $terms, string $query) {
                    $id = preg_match('/^(?:enq-?)?0*(\d{1,9})$/i', $query, $match) ? (int) $match[1] : null;

                    return Enquiry::query()
                        ->where(fn (Builder $where) => $where
                            ->where(fn (Builder $q) => $this->matchAll($q, ['CAST(data AS CHAR)'], $terms))
                            ->when($id, fn (Builder $q) => $q->orWhere('id', $id)))
                        ->latest();
                },
                'item' => fn (Enquiry $enquiry) => [
                    'title' => $enquiry->display_name,
                    'subtitle' => collect([$enquiry->reference, $enquiry->field('subject') ?? Str::limit((string) $enquiry->field('message'), 60), LocalTime::date($enquiry->created_at)])->filter()->join(' · '),
                    'badge' => EnquiryStatus::tryFrom((string) $enquiry->status)?->label(),
                    'url' => route('admin.enquiries.show', $enquiry),
                ],
                'index' => fn (string $query) => route('admin.enquiries.index', ['search' => $query]),
            ],
            'users' => [
                'label' => 'Users',
                'icon' => 'users',
                'permission' => 'admin.users.view',
                'query' => fn (array $terms) => User::query()
                    ->tap(fn (Builder $q) => $this->matchAll($q, ['name', 'email'], $terms))
                    ->with('roles:id,name')
                    ->orderBy('name'),
                'item' => fn (User $record, User $viewer) => [
                    'title' => $record->name,
                    'subtitle' => collect([$record->email, $record->roles->pluck('name')->map(fn ($role) => Str::title($role))->join(', ')])->filter()->join(' · '),
                    'badge' => $this->statusBadge($record->status),
                    'url' => $viewer->can('admin.users.edit') ? route('admin.users.edit', $record) : route('admin.users.index', ['search' => $record->email]),
                ],
                'index' => fn (string $query) => route('admin.users.index', ['search' => $query]),
            ],
            'blogs' => [
                'label' => 'Blog posts',
                'icon' => 'newspaper',
                'permission' => 'admin.blogs.view',
                'query' => fn (array $terms) => Blog::query()
                    ->tap(fn (Builder $q) => $this->matchAll($q, ['title', 'slug', 'excerpt'], $terms))
                    ->latest(),
                'item' => fn (Blog $blog, User $viewer) => [
                    'title' => $blog->title,
                    'subtitle' => '/'.$blog->slug,
                    'badge' => $this->statusBadge($blog->status),
                    'url' => $viewer->can('admin.blogs.edit') ? route('admin.blogs.edit', $blog) : route('admin.blogs.index', ['search' => $blog->title]),
                ],
                'index' => fn (string $query) => route('admin.blogs.index', ['search' => $query]),
            ],
            'blog-categories' => [
                'label' => 'Blog categories',
                'icon' => 'tag',
                'permission' => 'admin.blog-categories.view',
                'query' => fn (array $terms) => BlogCategory::query()
                    ->tap(fn (Builder $q) => $this->matchAll($q, ['name', 'slug'], $terms))
                    ->orderBy('name'),
                'item' => fn (BlogCategory $category, User $viewer) => [
                    'title' => $category->name,
                    'subtitle' => '/'.$category->slug,
                    'badge' => $this->statusBadge($category->status),
                    'url' => $viewer->can('admin.blog-categories.edit') ? route('admin.blog-categories.edit', $category) : route('admin.blog-categories.index', ['search' => $category->name]),
                ],
                'index' => fn (string $query) => route('admin.blog-categories.index', ['search' => $query]),
            ],
            'pages' => [
                'label' => 'Pages',
                'icon' => 'file-text',
                'permission' => 'admin.pages.view',
                'query' => fn (array $terms) => Page::query()
                    ->tap(fn (Builder $q) => $this->matchAll($q, ['title', 'slug'], $terms))
                    ->orderBy('title'),
                'item' => fn (Page $page, User $viewer) => [
                    'title' => $page->title,
                    'subtitle' => '/'.$page->slug,
                    'badge' => $this->statusBadge($page->status),
                    'url' => $viewer->can('admin.pages.edit') ? route('admin.pages.edit', $page) : route('admin.pages.index', ['search' => $page->title]),
                ],
                'index' => fn (string $query) => route('admin.pages.index', ['search' => $query]),
            ],
            'galleries' => [
                'label' => 'Galleries',
                'icon' => 'image',
                'permission' => 'admin.galleries.view',
                'query' => fn (array $terms) => Gallery::query()
                    ->tap(fn (Builder $q) => $this->matchAll($q, ['title', 'slug'], $terms))
                    ->orderBy('title'),
                'item' => fn (Gallery $gallery, User $viewer) => [
                    'title' => $gallery->title,
                    'subtitle' => $gallery->event_date ? LocalTime::date($gallery->event_date) : '/'.$gallery->slug,
                    'badge' => $this->statusBadge($gallery->status),
                    'url' => $viewer->can('admin.galleries.edit') ? route('admin.galleries.edit', $gallery) : route('admin.galleries.index', ['search' => $gallery->title]),
                ],
                'index' => fn (string $query) => route('admin.galleries.index', ['search' => $query]),
            ],
            'testimonials' => [
                'label' => 'Testimonials',
                'icon' => 'message',
                'permission' => 'admin.testimonials.view',
                'query' => fn (array $terms) => Testimonial::query()
                    ->tap(fn (Builder $q) => $this->matchAll($q, ['name', 'company', 'quote'], $terms))
                    ->orderBy('name'),
                'item' => fn (Testimonial $testimonial, User $viewer) => [
                    'title' => $testimonial->name,
                    'subtitle' => collect([$testimonial->company, Str::limit((string) $testimonial->quote, 60)])->filter()->join(' · '),
                    'badge' => $this->statusBadge($testimonial->status),
                    'url' => $viewer->can('admin.testimonials.edit') ? route('admin.testimonials.edit', $testimonial) : route('admin.testimonials.index', ['search' => $testimonial->name]),
                ],
                'index' => fn (string $query) => route('admin.testimonials.index', ['search' => $query]),
            ],
            'redirects' => [
                'label' => 'Redirects',
                'icon' => 'redirect',
                'permission' => 'admin.redirects.view',
                'query' => fn (array $terms) => Redirect::query()
                    ->tap(fn (Builder $q) => $this->matchAll($q, ['source_path', 'target_url', 'note'], $terms))
                    ->orderBy('source_path'),
                'item' => fn (Redirect $redirect, User $viewer) => [
                    'title' => $redirect->source_path,
                    'subtitle' => '→ '.$redirect->target_url.' ('.$redirect->status_code.')',
                    'badge' => $this->statusBadge($redirect->status),
                    'url' => $viewer->can('admin.redirects.edit') ? route('admin.redirects.edit', $redirect) : route('admin.redirects.index', ['search' => $redirect->source_path]),
                ],
                'index' => fn (string $query) => route('admin.redirects.index', ['search' => $query]),
            ],
            'seo' => [
                'label' => 'SEO',
                'icon' => 'globe',
                'permission' => 'admin.seo.view',
                'query' => fn (array $terms) => Seo::query()
                    ->tap(fn (Builder $q) => $this->matchAll($q, ['page', 'slug', 'meta_title'], $terms))
                    ->orderBy('page'),
                'item' => fn (Seo $seo, User $viewer) => [
                    'title' => $seo->meta_title ?: Str::headline((string) ($seo->page ?: $seo->slug)),
                    'subtitle' => collect([$seo->page, $seo->slug ? '/'.$seo->slug : null])->filter()->join(' · '),
                    'badge' => null,
                    'url' => $viewer->can('admin.seo.edit') ? route('admin.seo.edit', $seo) : route('admin.seo.index', ['search' => $seo->page ?: $seo->slug]),
                ],
                'index' => fn (string $query) => route('admin.seo.index', ['search' => $query]),
            ],
            'media' => [
                'label' => 'Media files',
                'icon' => 'folder',
                'permission' => 'admin.media.view',
                'query' => fn (array $terms) => MediaFile::query()
                    ->tap(fn (Builder $q) => $this->matchAll($q, ['filename', 'path'], $terms))
                    ->latest('id'),
                'item' => fn (MediaFile $file) => [
                    'title' => $file->filename,
                    'subtitle' => collect([$file->folder ?: 'Top folder', $file->size ? $this->size((int) $file->size) : null])->filter()->join(' · '),
                    'badge' => $file->extension ? Str::upper($file->extension) : null,
                    'url' => route('admin.media-library.index', ['search' => $file->filename]),
                ],
                'index' => fn (string $query) => route('admin.media-library.index', ['search' => $query]),
            ],
        ];
    }

    private function terms(string $query): array
    {
        return collect(preg_split('/\s+/', mb_strtolower($query)))
            ->filter()
            ->unique()
            ->take(5)
            ->map(fn (string $term) => '%'.addcslashes($term, '%_\\').'%')
            ->values()
            ->all();
    }

    private function matchAll(Builder $query, array $columns, array $terms): void
    {
        foreach ($terms as $term) {
            $query->where(function (Builder $where) use ($columns, $term) {
                foreach ($columns as $column) {
                    $where->orWhereRaw('LOWER('.$column.') LIKE ?', [$term]);
                }
            });
        }
    }

    private function statusBadge(mixed $status): ?string
    {
        $value = $status instanceof \BackedEnum ? $status->value : (string) $status;

        return match ($value) {
            'inactive' => 'Inactive',
            'draft' => 'Draft',
            default => null,
        };
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : max(1, round($bytes / 1024)).' KB';
    }
}
