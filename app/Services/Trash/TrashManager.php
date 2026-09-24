<?php

namespace App\Services\Trash;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Enquiry;
use App\Models\Page;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TrashManager
{
    public function types(): array
    {
        return [
            'blogs' => [
                'model' => Blog::class,
                'label' => 'Blog posts',
                'singular' => 'blog post',
                'icon' => 'newspaper',
                'permission' => 'admin.blogs.view',
                'title' => fn (Blog $m) => $m->title,
                'subtitle' => fn (Blog $m) => ($slug = $m->deleted_data['slug'] ?? null) ? '/'.$slug : null,
                'slug' => true,
                'files' => ['featured_image'],
                'edit' => fn (Blog $m) => route('admin.blogs.edit', $m),
            ],
            'pages' => [
                'model' => Page::class,
                'label' => 'Pages',
                'singular' => 'page',
                'icon' => 'file-text',
                'permission' => 'admin.pages.view',
                'title' => fn (Page $m) => $m->title,
                'subtitle' => fn (Page $m) => ($slug = $m->deleted_data['slug'] ?? null) ? '/'.$slug : null,
                'slug' => true,
                'files' => ['featured_image'],
                'edit' => fn (Page $m) => route('admin.pages.edit', $m),
            ],
            'blog-categories' => [
                'model' => BlogCategory::class,
                'label' => 'Categories',
                'singular' => 'category',
                'icon' => 'tag',
                'permission' => 'admin.blog-categories.view',
                'title' => fn (BlogCategory $m) => $m->name,
                'subtitle' => fn (BlogCategory $m) => $m->deleted_data['slug'] ?? null,
                'slug' => true,
                'files' => ['image'],
                'edit' => fn (BlogCategory $m) => route('admin.blog-categories.edit', $m),
            ],
            'testimonials' => [
                'model' => Testimonial::class,
                'label' => 'Testimonials',
                'singular' => 'testimonial',
                'icon' => 'message',
                'permission' => 'admin.testimonials.view',
                'title' => fn (Testimonial $m) => $m->name,
                'subtitle' => fn (Testimonial $m) => $m->by_line,
                'slug' => false,
                'files' => ['photo'],
                'edit' => fn (Testimonial $m) => route('admin.testimonials.edit', $m),
            ],
            'users' => [
                'model' => User::class,
                'label' => 'Users',
                'singular' => 'user',
                'icon' => 'users',
                'permission' => 'admin.users.view',
                'title' => fn (User $m) => $m->name,
                'subtitle' => fn (User $m) => $m->email,
                'slug' => false,
                'files' => ['avatar'],
                'edit' => fn (User $m) => route('admin.users.edit', $m),
            ],
            'enquiries' => [
                'model' => Enquiry::class,
                'label' => 'Enquiries',
                'singular' => 'enquiry',
                'icon' => 'inbox',
                'permission' => 'admin.enquiries.view',
                'title' => fn (Enquiry $m) => data_get($m->data, 'name') ?: data_get($m->data, 'email') ?: "Enquiry #{$m->id}",
                'subtitle' => fn (Enquiry $m) => collect([data_get($m->data, 'email'), $m->source])->filter()->implode(' · ') ?: null,
                'slug' => false,
                'files' => [],
                'edit' => fn (Enquiry $m) => route('admin.enquiries.index'),
            ],
        ];
    }

    public function typesFor(Authenticatable $user): array
    {
        return array_filter($this->types(), fn ($type) => $user->can($type['permission']));
    }

    public function counts(Authenticatable $user): array
    {
        return array_map(fn ($type) => $type['model']::onlyTrashed()->count(), $this->typesFor($user));
    }

    public function items(Authenticatable $user, ?string $only = null, ?string $search = null, string $sort = 'newest'): Collection
    {
        $rows = collect();

        foreach ($this->typesFor($user) as $key => $type) {
            if ($only && $only !== $key) {
                continue;
            }

            foreach ($type['model']::onlyTrashed()->get() as $model) {
                $rows->push($this->row($key, $type, $model));
            }
        }

        if (filled($search)) {
            $needle = Str::lower(trim($search));
            $rows = $rows->filter(fn ($row) => Str::contains(Str::lower($row['title'].' '.$row['subtitle']), $needle));
        }

        return ($sort === 'oldest' ? $rows->sortBy('deleted_at') : $rows->sortByDesc('deleted_at'))->values();
    }

    public function restore(Authenticatable $user, array $keys): array
    {
        $count = 0;
        $notes = [];

        foreach ($this->resolve($user, $keys) as [$key, $type, $model]) {
            DB::transaction(function () use ($type, $model, &$notes) {
                if ($type['slug']) {
                    $notes = [...$notes, ...$this->freeSlug($type, $model)];
                }

                $model->restore();
            });

            $count++;
        }

        return ['count' => $count, 'notes' => $notes];
    }

    public function forceDelete(?Authenticatable $user, array $keys): array
    {
        $items = $user ? $this->resolve($user, $keys) : $keys;
        $count = 0;
        $notes = [];

        foreach ($items as [$key, $type, $model]) {
            if ($reason = $this->blocker($user, $key, $model)) {
                $notes[] = $reason;

                continue;
            }

            $files = collect($type['files'])->map(fn ($field) => $model->{$field})->filter()->all();

            $model->forceDelete();
            Storage::disk('public')->delete($files);
            $count++;
        }

        return ['count' => $count, 'notes' => $notes];
    }

    public function allKeys(Authenticatable $user, ?string $only = null): array
    {
        return $this->items($user, $only)->pluck('key')->all();
    }

    public function purge(): int
    {
        $days = config('trash.purge_after_days');

        if (! $days) {
            return 0;
        }

        $cutoff = now()->subDays($days);
        $items = [];

        foreach ($this->types() as $key => $type) {
            foreach ($type['model']::onlyTrashed()->where('deleted_at', '<', $cutoff)->get() as $model) {
                $items[] = [$key, $type, $model];
            }
        }

        return $this->forceDelete(null, $items)['count'];
    }

    public function purgeDate(Carbon $deletedAt): ?Carbon
    {
        $days = config('trash.purge_after_days');

        return $days ? $deletedAt->copy()->addDays($days) : null;
    }

    private function row(string $key, array $type, Model $model): array
    {
        return [
            'key' => "{$key}:{$model->getKey()}",
            'type' => $key,
            'type_label' => Str::ucfirst($type['singular']),
            'icon' => $type['icon'],
            'id' => $model->getKey(),
            'title' => (string) (($type['title'])($model) ?: Str::ucfirst($type['singular'])." #{$model->getKey()}"),
            'subtitle' => ($type['subtitle'])($model),
            'deleted_at' => $model->deleted_at,
            'purge_at' => $this->purgeDate($model->deleted_at),
        ];
    }

    private function resolve(Authenticatable $user, array $keys): array
    {
        $types = $this->typesFor($user);
        $resolved = [];

        foreach (array_unique($keys) as $key) {
            [$typeKey, $id] = array_pad(explode(':', (string) $key, 2), 2, null);

            if (! isset($types[$typeKey]) || ! ctype_digit((string) $id)) {
                continue;
            }

            if ($model = $types[$typeKey]['model']::onlyTrashed()->find($id)) {
                $resolved[] = ["{$typeKey}:{$id}", $types[$typeKey], $model];
            }
        }

        return $resolved;
    }

    private function blocker(?Authenticatable $actor, string $key, Model $model): ?string
    {
        if (! $model instanceof User) {
            return null;
        }

        $posts = Blog::withTrashed()->where('user_id', $model->id)->count();
        if ($posts) {
            return "{$model->name} still has {$posts} blog ".Str::plural('post', $posts).'. Delete or reassign them first.';
        }

        if ($actor && $model->hasRole(['super admin', 'super-admin']) && ! $actor->hasRole(['super admin', 'super-admin'])) {
            return "Only a super admin can permanently delete {$model->name}.";
        }

        return null;
    }

    private function freeSlug(array $type, Model $model): array
    {
        $data = $model->deleted_data ?? [];
        $slug = $data['slug'] ?? null;

        if (! $slug) {
            return [];
        }

        $taken = fn (string $candidate) => $type['model']::withTrashed()->where('slug', $candidate)->whereKeyNot($model->getKey())->exists();

        if (! $taken($slug)) {
            return [];
        }

        $new = "{$slug}-restored";
        for ($i = 2; $taken($new); $i++) {
            $new = "{$slug}-restored-{$i}";
        }

        $model->deleted_data = [...$data, 'slug' => $new];
        $model->saveQuietly();

        $title = ($type['title'])($model);

        return ["“{$title}” was restored with the address “{$new}”, because “{$slug}” is now used by another {$type['singular']}."];
    }
}
