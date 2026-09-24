<?php

namespace App\Services\Media;

use App\Models\GalleryItem;
use App\Models\MediaFile;
use App\Models\MediaFileVersion;
use App\Models\User;
use App\Services\GalleryMediaProcessor;
use App\Support\ImageTool;
use App\Support\SvgGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaWriter
{
    public const FIT_KEEP = 'keep';

    public const FIT_COVER = 'cover';

    public function __construct(private MediaLibrary $library, private GalleryMediaProcessor $gallery) {}

    public function replace(MediaFile $file, string $upload, string $fit, ?User $user): MediaFile
    {
        $contents = $this->prepareReplacement($file, $upload, $fit);

        return DB::transaction(function () use ($file, $contents, $user) {
            $this->backup($file, $user);
            $this->write($file, $contents);

            return $file;
        });
    }

    public function restore(MediaFile $file, MediaFileVersion $version, ?User $user): MediaFile
    {
        $contents = Storage::disk('local')->get($version->backup_path);

        if ($contents === null) {
            throw ValidationException::withMessages(['version' => 'This version is no longer available.']);
        }

        return DB::transaction(function () use ($file, $version, $contents, $user) {
            $version->delete();
            $this->backup($file, $user);
            $this->write($file, $contents);

            return $file;
        });
    }

    public function storeNew(string $upload, string $originalName, string $folder): MediaFile
    {
        $mime = $this->mime($upload);
        $extension = $this->extensionFor($mime);

        $contents = match (true) {
            $extension === 'svg' => $this->checkedSvg($upload),
            $extension === 'ico', $extension === 'gif' => file_get_contents($upload),
            default => $this->rasterBytes($upload, $mime, $extension, null),
        };

        $path = $this->freePath($folder, $originalName, $extension);
        $absolute = $this->library->absolutePath(MediaFile::STORAGE, $path);

        if (! is_dir(dirname($absolute)) && ! @mkdir(dirname($absolute), 0775, true) && ! is_dir(dirname($absolute))) {
            throw ValidationException::withMessages(['file' => 'The upload folder could not be created.']);
        }

        if (file_put_contents($absolute, $contents) === false) {
            throw ValidationException::withMessages(['file' => 'The file could not be saved. Check the folder permissions.']);
        }

        return MediaFile::updateOrCreate(
            ['location' => MediaFile::STORAGE, 'path' => $path],
            [...$this->library->describe(MediaFile::STORAGE, $path), 'usage_count' => 0],
        );
    }

    private function prepareReplacement(MediaFile $file, string $upload, string $fit): string
    {
        $mime = $this->mime($upload);

        if (! in_array($mime, $file->acceptedUploadTypes(), true)) {
            throw ValidationException::withMessages(['file' => match ($file->extension) {
                'svg' => 'An SVG image can only be replaced by another SVG file.',
                'ico' => 'An .ico icon can only be replaced by another .ico file.',
                default => 'Please upload a JPG, PNG, WebP or GIF image.',
            }]);
        }

        if ($file->extension === 'svg') {
            return $this->checkedSvg($upload);
        }

        if ($file->extension === 'ico') {
            return file_get_contents($upload);
        }

        if ($file->extension === 'gif' && $mime === 'image/gif' && $fit === self::FIT_KEEP) {
            return file_get_contents($upload);
        }

        $target = $fit === self::FIT_COVER && $file->width && $file->height ? [$file->width, $file->height] : null;

        return $this->rasterBytes($upload, $mime, $file->extension, $target);
    }

    private function rasterBytes(string $upload, string $mime, string $extension, ?array $target): string
    {
        $image = ImageTool::load($upload, $mime);
        $max = config('media.max_dimension');

        $image = $target ? ImageTool::cover($image, $target[0], $target[1]) : ImageTool::fit($image, $max, $max);

        return ImageTool::encode($image, $extension, config('media.quality'));
    }

    private function backup(MediaFile $file, ?User $user): void
    {
        $absolute = $file->absolutePath();

        if (! is_file($absolute)) {
            return;
        }

        $backup = "media-versions/{$file->id}/".now()->format('YmdHis').'-'.Str::random(6).".{$file->extension}";
        Storage::disk('local')->put($backup, file_get_contents($absolute));

        $file->versions()->create([
            'backup_path' => $backup,
            'size' => $file->size,
            'width' => $file->width,
            'height' => $file->height,
            'user_id' => $user?->id,
        ]);

        $file->versions()->skip(config('media.keep_versions'))->take(PHP_INT_MAX)->get()->each->delete();
    }

    private function write(MediaFile $file, string $contents): void
    {
        $absolute = $file->absolutePath();
        $temp = $absolute.'.'.Str::random(8).'.tmp';

        if (file_put_contents($temp, $contents) === false || ! @rename($temp, $absolute)) {
            @unlink($temp);
            throw ValidationException::withMessages(['file' => 'The file could not be written. Check the folder permissions.']);
        }

        $mtime = max(time(), ($file->modified_at?->timestamp ?? 0) + 1);
        touch($absolute, $mtime);
        clearstatcache(true, $absolute);

        $this->library->forgetThumbnails($file);
        $file->fill($this->library->describe($file->location, $file->path));
        $file->version++;
        $file->save();

        if ($file->location === MediaFile::STORAGE) {
            GalleryItem::where('path', $file->path)->where('type', GalleryItem::IMAGE)->get()
                ->each(fn (GalleryItem $item) => rescue(fn () => $this->gallery->refreshImage($item), report: true));
        }
    }

    private function checkedSvg(string $upload): string
    {
        $contents = file_get_contents($upload);

        if ($problem = SvgGuard::problem($contents)) {
            throw ValidationException::withMessages(['file' => $problem]);
        }

        return $contents;
    }

    private function mime(string $file): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file) ?: '';

        if (in_array($mime, ['text/xml', 'application/xml', 'text/plain', 'text/html'], true) && stripos((string) file_get_contents($file, false, null, 0, 2048), '<svg') !== false) {
            return 'image/svg+xml';
        }

        if ($mime === 'image/x-icon' || $mime === 'image/vnd.microsoft.icon' || str_starts_with((string) file_get_contents($file, false, null, 0, 4), "\x00\x00\x01\x00")) {
            return 'image/x-icon';
        }

        return $mime;
    }

    private function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'image/x-icon' => 'ico',
            default => throw ValidationException::withMessages(['file' => "This file's contents are not a supported image (JPG, PNG, WebP, GIF, SVG or ICO)."]),
        };
    }

    private function freePath(string $folder, string $originalName, string $extension): string
    {
        $base = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'image';
        $base = Str::limit($base, 80, '');
        $prefix = $folder === '' ? '' : $folder.'/';
        $path = "{$prefix}{$base}.{$extension}";

        $root = $this->library->root(MediaFile::STORAGE);

        for ($i = 2; file_exists("{$root}/{$path}"); $i++) {
            $path = "{$prefix}{$base}-{$i}.{$extension}";
        }

        return $path;
    }
}
