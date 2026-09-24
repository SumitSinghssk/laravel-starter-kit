<?php

namespace App\Services\Media;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\GalleryItem;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\Seo;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class MediaUsageFinder
{
    private const REFERENCE = '~[^\s"\'<>()\[\],;{}]+\.(?:jpe?g|png|webp|gif|svg|ico)(?=[\s"\'<>()\[\],;?#{}]|$)~i';

    private array $usages = [];

    public function __construct(private MediaLibrary $library) {}

    public function find(): array
    {
        $this->usages = [];

        $this->records(Blog::class, 'Blog post', 'title', ['featured_image'], ['content', 'excerpt'], fn ($m) => route('admin.blogs.edit', $m));
        $this->records(Page::class, 'Page', 'title', ['featured_image'], ['content'], fn ($m) => route('admin.pages.edit', $m));
        $this->records(BlogCategory::class, 'Blog category', 'name', ['image'], ['description'], fn ($m) => route('admin.blog-categories.edit', $m));
        $this->records(User::class, 'User', 'name', ['avatar'], [], fn ($m) => route('admin.users.edit', $m));
        $this->records(Testimonial::class, 'Testimonial', 'name', ['photo'], [], fn ($m) => route('admin.testimonials.edit', $m));
        $this->records(Seo::class, 'SEO', 'page', ['og_image'], ['schema', 'header_scripts', 'footer_scripts', 'custom_css'], fn ($m) => route('admin.seo.edit', $m));
        $this->records(GalleryItem::class, 'Gallery', 'title', ['path', 'thumbnail_path'], [], fn ($m) => route('admin.galleries.edit', $m->gallery_id), fn ($m) => $m->gallery?->title);
        $this->settings();
        $this->files();

        return $this->usages;
    }

    private function records(string $model, string $source, string $titleColumn, array $fields, array $content, callable $url, ?callable $title = null): void
    {
        if (! Schema::hasTable((new $model)->getTable())) {
            return;
        }

        $softDeletes = in_array(SoftDeletes::class, class_uses_recursive($model), true);

        $model::query()
            ->when($softDeletes, fn ($q) => $q->withTrashed())
            ->when($model === GalleryItem::class, fn ($q) => $q->with('gallery:id,title'))
            ->chunkById(200, function ($records) use ($source, $titleColumn, $fields, $content, $url, $title, $softDeletes) {
                foreach ($records as $record) {
                    $trashed = $softDeletes && $record->trashed();
                    $label = ($title ? $title($record) : $record->{$titleColumn}).($trashed ? ' (in trash)' : '');
                    $link = $trashed ? fn () => route('admin.trash.index') : fn () => $url($record);

                    foreach ($fields as $field) {
                        if (filled($record->{$field})) {
                            $this->add(MediaFile::STORAGE, $record->{$field}, $source, $label, $this->fieldName($field), $link);
                        }
                    }

                    foreach ($content as $field) {
                        $this->references((string) $record->{$field}, $source, $label, $this->fieldName($field), $link);
                    }
                }
            });
    }

    private function settings(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $link = fn () => route('admin.settings.index');

        foreach (Setting::all() as $setting) {
            $value = $setting->value;

            if ($setting->key === 'basic_settings' && is_array($value)) {
                foreach (['logo.light' => 'Logo (light)', 'logo.dark' => 'Logo (dark)', 'favicon' => 'Favicon'] as $key => $name) {
                    if (filled($path = data_get($value, $key))) {
                        $this->add(MediaFile::STORAGE, $path, 'Site settings', $name, null, $link);
                    }
                }
            }

            $this->references(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '', 'Site settings', str($setting->key)->headline()->toString(), null, $link);
        }
    }

    private function files(): void
    {
        foreach ([resource_path('views'), resource_path('css')] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                if (! preg_match('/\.(blade\.php|css)$/', $file->getFilename())) {
                    continue;
                }

                $relative = str_replace('\\', '/', $file->getRelativePathname());
                $source = str_ends_with($relative, '.css') ? 'Stylesheet' : 'Template';
                $this->references($file->getContents(), $source, $relative, null, null, asAssetPaths: true);
            }
        }
    }

    private function references(string $text, string $source, ?string $title, ?string $detail, ?callable $url, bool $asAssetPaths = false): void
    {
        if ($text === '' || ! preg_match_all(self::REFERENCE, $text, $matches)) {
            return;
        }

        foreach (array_unique($matches[0]) as $reference) {
            $located = $this->library->locate($asAssetPaths && ! preg_match('#^(https?:)?//#i', $reference) ? '/'.ltrim($reference, '/') : $reference);

            if ($located) {
                $this->add($located[0], $located[1], $source, $title, $detail, $url);
            }
        }
    }

    private function add(string $location, string $path, string $source, ?string $title, ?string $detail, ?callable $url): void
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $key = "{$location}:{$path}";

        foreach ($this->usages[$key] ?? [] as $usage) {
            if ($usage['source'] === $source && $usage['title'] === $title && $usage['detail'] === $detail) {
                return;
            }
        }

        $this->usages[$key][] = [
            'source' => $source,
            'title' => $title !== null ? mb_substr($title, 0, 255) : null,
            'detail' => $detail,
            'url' => $url ? $url() : null,
        ];
    }

    private function fieldName(string $column): string
    {
        return str($column)->replace('_', ' ')->ucfirst()->toString();
    }
}
