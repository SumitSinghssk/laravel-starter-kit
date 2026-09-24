<?php

use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\GalleryUpload;
use App\Models\User;
use App\Support\YouTube;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    config(['gallery.chunk_size' => 4096]);
});

function galleryAdmin(): User
{
    $role = Role::findOrCreate('super admin', 'web');
    $role->givePermissionTo(Permission::where('name', 'like', 'admin.galleries.%')->get());

    return tap(User::factory()->create())->assignRole($role);
}

function makeGallery(array $attributes = []): Gallery
{
    return Gallery::create(['title' => 'Annual Day', 'slug' => 'annual-day-'.uniqid(), 'status' => 'active', ...$attributes]);
}

function jpegBytes(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    for ($i = 0; $i < 400; $i++) {
        imagefilledrectangle($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), random_int(0, 0xFFFFFF));
    }
    ob_start();
    imagejpeg($image, null, 90);

    return ob_get_clean();
}

function mp4Bytes(int $size = 20000): string
{
    $ftyp = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";

    return $ftyp.str_repeat("\x00", $size - strlen($ftyp));
}

function uploadInChunks(Gallery $gallery, string $name, string $bytes, string $mime, array $complete = []): TestResponse
{
    $session = test()->postJson(route('admin.galleries.uploads.store', $gallery), [
        'name' => $name, 'size' => strlen($bytes), 'mime' => $mime, 'fingerprint' => "{$name}|".strlen($bytes),
    ])->assertOk()->json();

    foreach (str_split($bytes, $session['chunk_size']) as $index => $piece) {
        test()->post(str_replace('__INDEX__', $index, $session['chunk_url']), ['chunk' => UploadedFile::fake()->createWithContent('blob', $piece)], ['Accept' => 'application/json'])
            ->assertOk();
    }

    return test()->post($session['complete_url'], $complete, ['Accept' => 'application/json']);
}

describe('YouTube links', function () {
    test('the video id is found in every common format', function (string $input) {
        expect(YouTube::videoId($input))->toBe('dQw4w9WgXcQ');
    })->with([
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'https://youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=42',
        'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
        'https://youtu.be/dQw4w9WgXcQ?si=abc',
        'youtu.be/dQw4w9WgXcQ',
        'https://www.youtube.com/embed/dQw4w9WgXcQ?start=10',
        'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        'https://www.youtube.com/shorts/dQw4w9WgXcQ',
        'https://www.youtube.com/live/dQw4w9WgXcQ',
        '<iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ?si=x" title="YouTube video player" allowfullscreen></iframe>',
    ]);

    test('anything else is rejected', function (string $input) {
        expect(YouTube::videoId($input))->toBeNull();
    })->with([
        'https://vimeo.com/123456',
        'https://evil.com/watch?v=dQw4w9WgXcQ',
        'https://youtube.com.evil.com/watch?v=dQw4w9WgXcQ',
        'https://www.youtube.com/watch?v=short',
        'https://www.youtube.com/',
        'not a link',
    ]);
});

describe('albums', function () {
    test('an admin can create an album; the slug comes from the title', function () {
        $this->actingAs(galleryAdmin())
            ->post(route('admin.galleries.store'), ['title' => 'Team Outing 2026', 'slug' => '', 'status' => 'active', 'event_date' => '2026-03-14', 'is_featured' => 1])
            ->assertSessionHasNoErrors();

        expect(Gallery::sole())->slug->toBe('team-outing-2026')->is_featured->toBeTrue()->event_date->toDateString()->toBe('2026-03-14');
    });

    test('slugs must be unique', function () {
        makeGallery(['slug' => 'team-outing']);

        $this->actingAs(galleryAdmin())
            ->post(route('admin.galleries.store'), ['title' => 'Team Outing', 'slug' => 'Team Outing', 'status' => 'active'])
            ->assertSessionHasErrors('slug');
    });

    test('the album pages render', function () {
        $gallery = makeGallery();
        $gallery->items()->create(['type' => 'youtube', 'youtube_id' => 'dQw4w9WgXcQ', 'title' => 'Intro']);
        $this->actingAs(galleryAdmin());

        $this->get(route('admin.galleries.index'))->assertOk()->assertSee('Annual Day')->assertSee('i.ytimg.com/vi/dQw4w9WgXcQ', false);
        $this->get(route('admin.galleries.index', ['search' => 'nothing-matches']))->assertOk()->assertSee('No albums match');
        $this->get(route('admin.galleries.create'))->assertOk();
        $this->get(route('admin.galleries.edit', $gallery))->assertOk()->assertSee('galleryManager', false);
    });

    test('deleting an album deletes its media files and unfinished uploads', function () {
        $admin = galleryAdmin();
        $this->actingAs($admin);
        $gallery = makeGallery();
        uploadInChunks($gallery, 'photo.jpg', jpegBytes(800, 600), 'image/jpeg')->assertCreated();
        $item = GalleryItem::sole();

        $pending = $this->postJson(route('admin.galleries.uploads.store', $gallery), ['name' => 'b.jpg', 'size' => 5000, 'mime' => 'image/jpeg', 'fingerprint' => 'b'])->json();
        $this->post(str_replace('__INDEX__', 0, $pending['chunk_url']), ['chunk' => UploadedFile::fake()->createWithContent('b', str_repeat('x', 4096))], ['Accept' => 'application/json'])->assertOk();

        $this->delete(route('admin.galleries.destroy', $gallery))->assertRedirect(route('admin.galleries.index'));

        Storage::disk('public')->assertMissing([$item->path, $item->thumbnail_path]);
        Storage::disk('local')->assertMissing('gallery-chunks/'.$pending['id']);
        expect(Gallery::count())->toBe(0)->and(GalleryUpload::count())->toBe(0);
    });

    test('users without the permission cannot manage albums', function () {
        $gallery = makeGallery();
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.galleries.index'))->assertForbidden();
        $this->getJson(route('admin.galleries.media.index', $gallery))->assertForbidden();
        $this->postJson(route('admin.galleries.uploads.store', $gallery), ['name' => 'a.jpg', 'size' => 10, 'fingerprint' => 'a'])->assertForbidden();
    });
});

describe('chunked uploads', function () {
    test('an image is uploaded in chunks, resized, converted to WebP and given a thumbnail', function () {
        $this->actingAs(galleryAdmin());
        $gallery = makeGallery();
        $bytes = jpegBytes(3000, 1500);
        expect(strlen($bytes))->toBeGreaterThan(4096 * 3);

        $item = uploadInChunks($gallery, 'team_outing-2026.jpg', $bytes, 'image/jpeg')->assertCreated()->json('item');

        expect($item)->type->toBe('image')->width->toBe(2000)->height->toBe(1000)->title->toBe('Team outing 2026')->mime->toBe('image/webp');

        $stored = GalleryItem::sole();
        Storage::disk('public')->assertExists([$stored->path, $stored->thumbnail_path]);
        expect(getimagesizefromstring(Storage::disk('public')->get($stored->thumbnail_path))[0])->toBe(480);
        expect(GalleryUpload::count())->toBe(0);
        Storage::disk('local')->assertDirectoryEmpty('gallery-chunks');
    });

    test('palette PNGs (like logos) are converted too', function () {
        $this->actingAs(galleryAdmin());
        $image = imagecreate(300, 200);
        imagecolorallocate($image, 200, 30, 30);
        ob_start();
        imagepng($image);

        uploadInChunks(makeGallery(), 'logo.png', ob_get_clean(), 'image/png')->assertCreated()->assertJsonPath('item.width', 300);
    });

    test('a video is stored as uploaded with the poster, duration and size read in the browser', function () {
        $this->actingAs(galleryAdmin());

        $item = uploadInChunks(makeGallery(), 'clip.mp4', mp4Bytes(), 'video/mp4', [
            'thumbnail' => UploadedFile::fake()->image('poster.jpg', 1280, 720),
            'duration' => '12.6',
            'width' => 1280,
            'height' => 720,
        ])->assertCreated()->json('item');

        expect($item)->type->toBe('video')->duration->toBe(13)->width->toBe(1280)->size->toBe(20000);
        expect($item['url'])->toContain('/stream');

        $stored = GalleryItem::sole();
        expect($stored->path)->toEndWith('.mp4');
        Storage::disk('public')->assertExists([$stored->path, $stored->thumbnail_path]);
        expect(getimagesizefromstring(Storage::disk('public')->get($stored->thumbnail_path))[0])->toBe(640);
    });

    test('files over the size limit or of other types are refused before uploading', function (array $input) {
        $this->actingAs(galleryAdmin())
            ->postJson(route('admin.galleries.uploads.store', makeGallery()), ['fingerprint' => 'x', ...$input])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    })->with([
        'video over 10 MB' => [['name' => 'big.mp4', 'size' => 10 * 1024 * 1024 + 1, 'mime' => 'video/mp4']],
        'image over 10 MB' => [['name' => 'big.jpg', 'size' => 10 * 1024 * 1024 + 1, 'mime' => 'image/jpeg']],
        'pdf' => [['name' => 'cv.pdf', 'size' => 100, 'mime' => 'application/pdf']],
        'gif' => [['name' => 'a.gif', 'size' => 100, 'mime' => 'image/gif']],
    ]);

    test('picking the same file again resumes: the server reports the chunks it already has', function () {
        $this->actingAs(galleryAdmin());
        $gallery = makeGallery();
        $bytes = jpegBytes(1200, 900);
        $payload = ['name' => 'a.jpg', 'size' => strlen($bytes), 'mime' => 'image/jpeg', 'fingerprint' => 'a.jpg|1700000000'];

        $first = $this->postJson(route('admin.galleries.uploads.store', $gallery), $payload)->json();
        $pieces = str_split($bytes, $first['chunk_size']);
        foreach ([0, 1] as $index) {
            $this->post(str_replace('__INDEX__', $index, $first['chunk_url']), ['chunk' => UploadedFile::fake()->createWithContent('b', $pieces[$index])], ['Accept' => 'application/json'])->assertOk();
        }

        $again = $this->postJson(route('admin.galleries.uploads.store', $gallery), $payload)->json();

        expect($again['id'])->toBe($first['id'])->and($again['received'])->toBe([0, 1]);
        expect(GalleryUpload::count())->toBe(1);
    });

    test('a chunk is safe to send twice, and a short chunk is refused', function () {
        $this->actingAs(galleryAdmin());
        $session = $this->postJson(route('admin.galleries.uploads.store', makeGallery()), ['name' => 'a.jpg', 'size' => 10000, 'mime' => 'image/jpeg', 'fingerprint' => 'a'])->json();
        $url = str_replace('__INDEX__', 0, $session['chunk_url']);

        $this->post($url, ['chunk' => UploadedFile::fake()->createWithContent('b', str_repeat('a', 4096))], ['Accept' => 'application/json'])->assertOk();
        $this->post($url, ['chunk' => UploadedFile::fake()->createWithContent('b', str_repeat('a', 4096))], ['Accept' => 'application/json'])->assertOk();
        $this->post($url, ['chunk' => UploadedFile::fake()->createWithContent('b', 'short')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post(str_replace('__INDEX__', 99, $session['chunk_url']), ['chunk' => UploadedFile::fake()->createWithContent('b', 'x')], ['Accept' => 'application/json'])->assertUnprocessable();

        expect(GalleryUpload::sole()->receivedChunks())->toBe([0]);
    });

    test('finishing with missing chunks lists them so the browser can resend', function () {
        $this->actingAs(galleryAdmin());
        $session = $this->postJson(route('admin.galleries.uploads.store', makeGallery()), ['name' => 'a.jpg', 'size' => 10000, 'mime' => 'image/jpeg', 'fingerprint' => 'a'])->json();
        $this->post(str_replace('__INDEX__', 1, $session['chunk_url']), ['chunk' => UploadedFile::fake()->createWithContent('b', str_repeat('a', 4096))], ['Accept' => 'application/json'])->assertOk();

        $this->postJson($session['complete_url'])->assertUnprocessable()->assertJson(['missing' => [0, 2]]);
        expect(GalleryItem::count())->toBe(0);
    });

    test('a renamed file is caught by its contents and its chunks are deleted', function () {
        $this->actingAs(galleryAdmin());

        uploadInChunks(makeGallery(), 'holiday.jpg', str_repeat('<?php echo "hi"; ?>', 800), 'image/jpeg')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        expect(GalleryItem::count())->toBe(0)->and(GalleryUpload::count())->toBe(0);
    });

    test('someone else cannot send chunks to your upload', function () {
        $this->actingAs(galleryAdmin());
        $session = $this->postJson(route('admin.galleries.uploads.store', makeGallery()), ['name' => 'a.jpg', 'size' => 100, 'mime' => 'image/jpeg', 'fingerprint' => 'a'])->json();

        $this->actingAs(galleryAdmin())
            ->post(str_replace('__INDEX__', 0, $session['chunk_url']), ['chunk' => UploadedFile::fake()->createWithContent('b', str_repeat('a', 100))], ['Accept' => 'application/json'])
            ->assertNotFound();
    });

    test('cancelling deletes the chunks', function () {
        $this->actingAs(galleryAdmin());
        $session = $this->postJson(route('admin.galleries.uploads.store', makeGallery()), ['name' => 'a.jpg', 'size' => 5000, 'mime' => 'image/jpeg', 'fingerprint' => 'a'])->json();
        $this->post(str_replace('__INDEX__', 0, $session['chunk_url']), ['chunk' => UploadedFile::fake()->createWithContent('b', str_repeat('a', 4096))], ['Accept' => 'application/json']);

        $this->deleteJson($session['cancel_url'])->assertNoContent();

        Storage::disk('local')->assertMissing('gallery-chunks/'.$session['id']);
        expect(GalleryUpload::count())->toBe(0);
    });

    test('abandoned uploads are pruned after a day', function () {
        $this->actingAs(galleryAdmin());
        $gallery = makeGallery();
        $old = GalleryUpload::create(['gallery_id' => $gallery->id, 'user_id' => auth()->id(), 'fingerprint' => 'old', 'original_name' => 'a.jpg', 'kind' => 'image', 'size' => 10, 'chunk_size' => 10, 'total_chunks' => 1]);
        Storage::disk('local')->put($old->chunkPath(0), 'x');
        $old->forceFill(['updated_at' => now()->subHours(25)])->saveQuietly();

        $this->artisan('gallery:prune-uploads')->assertSuccessful();

        expect(GalleryUpload::count())->toBe(0);
        Storage::disk('local')->assertMissing($old->chunkDirectory());
    });
});

describe('media', function () {
    test('media is listed in batches without skipping items after deletes', function () {
        config(['gallery.per_page' => 2]);
        $this->actingAs(galleryAdmin());
        $gallery = makeGallery();
        $items = collect(range(1, 5))->map(fn ($i) => $gallery->items()->create(['type' => 'youtube', 'youtube_id' => str_pad("vid{$i}", 11, 'x'), 'sort_order' => $i]));

        $first = $this->getJson(route('admin.galleries.media.index', $gallery))->assertOk();
        expect($first->json('data.*.id'))->toBe([$items[0]->id, $items[1]->id])->and($first->json('total'))->toBe(5);

        $items[0]->delete();
        $second = $this->getJson($first->json('next_page_url'))->json();

        expect(collect($second['data'])->pluck('id')->all())->toBe([$items[2]->id, $items[3]->id]);
    });

    test('YouTube videos can be added by link or embed code, with the title from YouTube', function () {
        Http::fake(['www.youtube.com/oembed*' => Http::response(['title' => 'Our 2026 Highlights'])]);
        $this->actingAs(galleryAdmin());
        $gallery = makeGallery();

        $item = $this->postJson(route('admin.galleries.media.youtube', $gallery), ['url' => 'https://youtu.be/dQw4w9WgXcQ'])->assertCreated()->json('item');

        expect($item)->type->toBe('youtube')->title->toBe('Our 2026 Highlights')->url->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
            ->thumb->toBe('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg');

        $this->postJson(route('admin.galleries.media.youtube', $gallery), ['url' => '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>'])
            ->assertUnprocessable()->assertJsonValidationErrors(['url' => 'already in the album']);
        $this->postJson(route('admin.galleries.media.youtube', $gallery), ['url' => 'https://vimeo.com/1'])->assertJsonValidationErrors('url');
    });

    test('a YouTube video is still added when YouTube cannot be reached', function () {
        Http::fake(['*' => Http::response(null, 500)]);
        $this->actingAs(galleryAdmin());

        $this->postJson(route('admin.galleries.media.youtube', makeGallery()), ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => ''])
            ->assertCreated()
            ->assertJsonPath('item.title', null);
    });

    test('title, caption and a custom video thumbnail can be changed', function () {
        $this->actingAs(galleryAdmin());
        $gallery = makeGallery();
        $item = $gallery->items()->create(['type' => 'youtube', 'youtube_id' => 'dQw4w9WgXcQ']);

        $data = $this->post(route('admin.gallery-media.update', $item), [
            'title' => 'Opening speech',
            'caption' => 'Main hall, 9 AM',
            'thumbnail' => UploadedFile::fake()->image('thumb.png', 1920, 1080),
        ], ['Accept' => 'application/json'])->assertOk()->json('item');

        expect($data)->title->toBe('Opening speech')->caption->toBe('Main hall, 9 AM')->custom_thumb->toBeTrue();
        $path = $item->fresh()->thumbnail_path;
        Storage::disk('public')->assertExists($path);

        $this->post(route('admin.gallery-media.update', $item), ['remove_thumbnail' => 1], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('item.thumb', 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg');
        Storage::disk('public')->assertMissing($path);
    });

    test('reordering loaded items keeps them ahead of the ones not loaded yet', function () {
        $this->actingAs(galleryAdmin());
        $gallery = makeGallery();
        [$a, $b, $c, $d] = collect(['a', 'b', 'c', 'd'])->map(fn ($id, $i) => $gallery->items()->create(['type' => 'youtube', 'youtube_id' => str_pad($id, 11, 'x'), 'sort_order' => ($i + 1) * 10]))->all();

        $this->postJson(route('admin.galleries.media.reorder', $gallery), ['ids' => [$c->id, $a->id, $b->id]])->assertOk();

        expect($gallery->items()->pluck('id')->all())->toBe([$c->id, $a->id, $b->id, $d->id]);
    });

    test('the cover can be set, and deleting the cover item resets it', function () {
        $this->actingAs(galleryAdmin());
        $gallery = makeGallery();
        $item = $gallery->items()->create(['type' => 'youtube', 'youtube_id' => 'dQw4w9WgXcQ']);
        $other = makeGallery()->items()->create(['type' => 'youtube', 'youtube_id' => 'aaaaaaaaaaa']);

        $this->postJson(route('admin.galleries.media.cover', $gallery), ['item_id' => $other->id])->assertUnprocessable();
        $this->postJson(route('admin.galleries.media.cover', $gallery), ['item_id' => $item->id])->assertOk();
        expect($gallery->fresh()->cover_item_id)->toBe($item->id);

        $this->deleteJson(route('admin.gallery-media.destroy', $item))->assertOk();
        expect($gallery->fresh()->cover_item_id)->toBeNull();
    });

    test('bulk delete removes only this album\'s items and their files', function () {
        $this->actingAs(galleryAdmin());
        $gallery = makeGallery();
        uploadInChunks($gallery, 'a.jpg', jpegBytes(600, 400), 'image/jpeg')->assertCreated();
        $photo = GalleryItem::sole();
        $elsewhere = makeGallery()->items()->create(['type' => 'youtube', 'youtube_id' => 'dQw4w9WgXcQ']);

        $this->postJson(route('admin.galleries.media.bulk-destroy', $gallery), ['ids' => [$photo->id, $elsewhere->id]])
            ->assertOk()
            ->assertJson(['deleted' => [$photo->id]]);

        Storage::disk('public')->assertMissing([$photo->path, $photo->thumbnail_path]);
        expect($elsewhere->fresh())->not->toBeNull();
    });

    test('videos are streamed in pieces with HTTP Range requests', function () {
        $this->actingAs(galleryAdmin());
        $item = GalleryItem::find(uploadInChunks(makeGallery(), 'clip.mp4', mp4Bytes(), 'video/mp4')->json('item.id'));

        $this->get(route('admin.gallery-media.stream', $item), ['Range' => 'bytes=0-1023'])
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-1023/20000')
            ->assertHeader('Content-Length', '1024');

        $this->get(route('admin.gallery-media.stream', $item))->assertOk()->assertHeader('Accept-Ranges', 'bytes');
    });
});
