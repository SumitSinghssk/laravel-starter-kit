<?php

namespace App\Services;

use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Support\ImageTool;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GalleryMediaProcessor
{
    public function storeImage(Gallery $gallery, string $file, string $mime): array
    {
        $config = config('gallery.image');

        $full = ImageTool::fit(ImageTool::load($file, $mime), $config['max_dimension'], $config['max_dimension']);

        $name = Str::uuid();
        $path = $this->save($full, "gallery/{$gallery->id}/{$name}.webp");
        $thumbnailPath = $this->save($this->thumbnail($full), "gallery/{$gallery->id}/thumbs/{$name}.webp");

        return [
            'type' => GalleryItem::IMAGE,
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'width' => imagesx($full),
            'height' => imagesy($full),
            'size' => GalleryItem::disk()->size($path),
            'mime' => 'image/webp',
        ];
    }

    public function refreshImage(GalleryItem $item): void
    {
        $full = ImageTool::load(GalleryItem::disk()->path($item->path));

        if ($item->thumbnail_path) {
            GalleryItem::disk()->put($item->thumbnail_path, ImageTool::encode($this->thumbnail($full), 'webp', config('gallery.image.quality')));
        }

        $item->forceFill([
            'width' => imagesx($full),
            'height' => imagesy($full),
            'size' => GalleryItem::disk()->size($item->path),
        ])->save();
    }

    public function storeVideo(Gallery $gallery, string $file, string $mime, ?UploadedFile $poster = null): array
    {
        $extension = config("gallery.mimes.video.{$mime}");
        $path = "gallery/{$gallery->id}/videos/".Str::uuid().".{$extension}";

        $stream = fopen($file, 'rb');
        GalleryItem::disk()->writeStream($path, $stream);
        fclose($stream);

        return [
            'type' => GalleryItem::VIDEO,
            'path' => $path,
            'thumbnail_path' => $poster ? $this->storeThumbnail($gallery, $poster) : null,
            'size' => GalleryItem::disk()->size($path),
            'mime' => $mime,
        ];
    }

    public function storeThumbnail(Gallery $gallery, UploadedFile $file): string
    {
        $mime = $file->getMimeType();

        if (! array_key_exists($mime, config('gallery.mimes.image'))) {
            throw ValidationException::withMessages(['thumbnail' => 'The thumbnail must be a JPG, PNG or WebP image.']);
        }

        $width = config('gallery.poster_width');
        $image = ImageTool::fit(ImageTool::load($file->getRealPath(), $mime), $width, $width * 2);

        return $this->save($image, "gallery/{$gallery->id}/thumbs/".Str::uuid().'.webp');
    }

    private function thumbnail(\GdImage $image): \GdImage
    {
        $width = config('gallery.image.thumb_width');

        return ImageTool::fit($image, $width, $width * 2);
    }

    private function save(\GdImage $image, string $path): string
    {
        GalleryItem::disk()->put($path, ImageTool::encode($image, 'webp', config('gallery.image.quality')));

        return $path;
    }
}
