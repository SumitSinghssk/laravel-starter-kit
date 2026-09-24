<?php

use App\Models\Blog;
use App\Models\Gallery;
use App\Models\MediaFile;
use App\Models\MediaUpload;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Services\Media\MediaLibrary;
use App\Services\Media\MediaScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    Storage::fake('web-root');

    config([
        'media.roots' => ['storage' => Storage::disk('public')->path(''), 'public' => Storage::disk('web-root')->path('')],
        'media.chunk_size' => 4096,
    ]);
    Cache::flush();
});

function mediaAdmin(): User
{
    $role = Role::findOrCreate('super admin', 'web');
    $role->givePermissionTo(Permission::where('name', 'like', 'admin.media.%')->get());

    return tap(User::factory()->create())->assignRole($role);
}

function mediaImage(int $width, int $height, string $format = 'jpg'): string
{
    $image = imagecreatetruecolor($width, $height);
    for ($i = 0; $i < 300; $i++) {
        imagefilledellipse($image, random_int(0, $width), random_int(0, $height), random_int(5, 80), random_int(5, 80), random_int(0, 0xFFFFFF));
    }
    ob_start();
    match ($format) {
        'png' => imagepng($image),
        'webp' => imagewebp($image),
        'gif' => imagegif($image),
        default => imagejpeg($image, null, 92),
    };

    return ob_get_clean();
}

function scanMedia(): array
{
    return app(MediaScanner::class)->scan();
}

function mediaFile(string $path, string $location = 'storage'): MediaFile
{
    return MediaFile::where('location', $location)->where('path', $path)->firstOrFail();
}

function sendMedia(string $name, string $bytes, string $mime, array $start = [], array $complete = []): TestResponse
{
    $session = test()->postJson(route('admin.media-library.uploads.store'), [
        'name' => $name, 'size' => strlen($bytes), 'mime' => $mime, 'fingerprint' => $name.'|'.md5($bytes), ...$start,
    ])->assertOk()->json();

    foreach (str_split($bytes, $session['chunk_size']) as $index => $piece) {
        test()->post(str_replace('__INDEX__', $index, $session['chunk_url']), ['chunk' => UploadedFile::fake()->createWithContent('blob', $piece)], ['Accept' => 'application/json'])
            ->assertOk();
    }

    return test()->postJson($session['complete_url'], $complete);
}

describe('scanning', function () {
    test('images in storage and public are indexed; excluded folders, links and other files are not', function () {
        Storage::disk('public')->put('blogs/cover.jpg', mediaImage(1200, 600));
        Storage::disk('public')->put('gallery/1/thumbs/t.webp', mediaImage(100, 100, 'webp'));
        Storage::disk('public')->put('docs/readme.txt', 'hello');
        Storage::disk('web-root')->put('images/logo.png', mediaImage(300, 100, 'png'));
        Storage::disk('web-root')->put('build/assets/app.png', mediaImage(10, 10, 'png'));
        Storage::disk('web-root')->put('icons/mark.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 32"><rect width="64" height="32"/></svg>');

        $stats = scanMedia();

        expect($stats)->added->toBe(3);
        expect(MediaFile::orderBy('path')->get(['location', 'path'])->map(fn ($f) => "{$f->location}:{$f->path}")->all())
            ->toBe(['storage:blogs/cover.jpg', 'public:icons/mark.svg', 'public:images/logo.png']);

        expect(mediaFile('blogs/cover.jpg'))->width->toBe(1200)->height->toBe(600)->folder->toBe('blogs')->extension->toBe('jpg');
        expect(mediaFile('icons/mark.svg', 'public'))->width->toBe(64)->height->toBe(32);
    });

    test('a second scan only picks up changes, and forgets deleted files', function () {
        Storage::disk('public')->put('a.jpg', mediaImage(100, 100));
        Storage::disk('public')->put('b.jpg', mediaImage(100, 100));
        scanMedia();

        expect(scanMedia())->added->toBe(0)->updated->toBe(0)->changed->toBeFalse();

        Storage::disk('public')->delete('b.jpg');
        Storage::disk('public')->put('c.png', mediaImage(50, 50, 'png'));

        expect(scanMedia())->added->toBe(1)->removed->toBe(1)->changed->toBeTrue();
        expect(MediaFile::pluck('path')->sort()->values()->all())->toBe(['a.jpg', 'c.png']);
    });

    test('usage is found in image fields, rich text, settings and templates', function () {
        $user = mediaAdmin();
        Storage::disk('public')->put('blogs/cover.jpg', mediaImage(400, 200));
        Storage::disk('public')->put('uploads/inline.png', mediaImage(80, 80, 'png'));
        Storage::disk('public')->put('settings/logos/logo.png', mediaImage(200, 60, 'png'));
        Storage::disk('public')->put('orphan.jpg', mediaImage(50, 50));
        Storage::disk('web-root')->put('favicon.ico', "\x00\x00\x01\x00".str_repeat("\x00", 60));

        Blog::create(['title' => 'Launch post', 'slug' => 'launch', 'content' => '<p>x</p>', 'featured_image' => 'blogs/cover.jpg', 'status' => 'active', 'user_id' => $user->id]);
        Page::create(['title' => 'About us', 'slug' => 'about', 'content' => '<p><img src="'.url('storage/uploads/inline.png').'?v=3" alt=""></p>', 'status' => 'active', 'user_id' => $user->id]);
        Setting::create(['key' => 'basic_settings', 'value' => ['logo' => ['light' => 'settings/logos/logo.png', 'dark' => null]]]);

        scanMedia();

        expect(mediaFile('blogs/cover.jpg')->usages->pluck('source', 'title')->all())->toBe(['Launch post' => 'Blog post']);
        expect(mediaFile('uploads/inline.png')->usages->first())->source->toBe('Page')->title->toBe('About us')->detail->toBe('Content');
        expect(mediaFile('settings/logos/logo.png')->usages->first())->source->toBe('Site settings')->title->toBe('Logo (light)');
        expect(mediaFile('favicon.ico', 'public')->usages->pluck('title')->all())->toContain('layouts/partials/website/seo.blade.php');
        expect(mediaFile('orphan.jpg'))->usage_count->toBe(0);
    });

    test('paths can never leave their folder', function (string $path) {
        expect(fn () => app(MediaLibrary::class)->absolutePath('storage', $path))->toThrow(RuntimeException::class);
    })->with(['../.env', 'a/../../x.jpg', '/etc/passwd', 'C:/Windows/win.ini', '']);
});

describe('library pages', function () {
    test('the first visit builds the index, and filters work', function () {
        Storage::disk('public')->put('blogs/big-photo.jpg', mediaImage(2400, 1600));
        Storage::disk('public')->put('media/small.png', mediaImage(40, 40, 'png'));
        Storage::disk('web-root')->put('images/hero.webp', mediaImage(600, 300, 'webp'));
        config(['media.large_bytes' => 20 * 1024]);
        $this->actingAs(mediaAdmin());

        $this->get(route('admin.media-library.index'))->assertOk()->assertSee('big-photo.jpg')->assertSee('small.png')->assertSee('hero.webp');
        expect(MediaScanner::lastScan())->not->toBeNull();

        $this->get(route('admin.media-library.index', ['location' => 'public']))->assertSee('hero.webp')->assertDontSee('small.png');
        $this->get(route('admin.media-library.index', ['type' => 'png']))->assertSee('small.png')->assertDontSee('big-photo.jpg');
        $this->get(route('admin.media-library.index', ['search' => 'big']))->assertSee('big-photo.jpg')->assertDontSee('hero.webp');
        $this->get(route('admin.media-library.index', ['size' => 'large']))->assertSee('big-photo.jpg')->assertDontSee('small.png');
        $this->get(route('admin.media-library.index', ['folder' => 'storage:media']))->assertSee('small.png')->assertDontSee('big-photo.jpg');
        $this->get(route('admin.media-library.index', ['usage' => 'used']))->assertSee('No images match');
        $this->get(route('admin.media-library.index', ['sort' => 'largest', 'usage' => 'unused']))->assertOk()->assertSeeInOrder(['big-photo.jpg', 'hero.webp', 'small.png']);
    });

    test('details list usage, versions and what is allowed', function () {
        Storage::disk('web-root')->put('images/logo.png', mediaImage(300, 100, 'png'));
        scanMedia();
        $this->actingAs(mediaAdmin());

        $this->getJson(route('admin.media-library.show', mediaFile('images/logo.png', 'public')))
            ->assertOk()
            ->assertJson([
                'location' => 'public',
                'display_path' => 'public/images/logo.png',
                'dimensions' => '300 × 100',
                'can_replace' => true,
                'can_delete' => false,
                'accept' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            ]);
    });

    test('thumbnails are small cached WebPs; SVGs are sandboxed', function () {
        Storage::disk('public')->put('big.jpg', mediaImage(2000, 1000));
        Storage::disk('public')->put('icon.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>');
        scanMedia();
        $this->actingAs(mediaAdmin());

        $response = $this->get(mediaFile('big.jpg')->thumb_url)->assertOk()->assertHeader('Content-Type', 'image/webp');
        expect(getimagesize($response->baseResponse->getFile()->getPathname())[0])->toBe(config('media.thumb_size'));

        $this->get(mediaFile('icon.svg')->thumb_url)->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; img-src data:; style-src 'unsafe-inline'; sandbox");
    });

    test('users without the permission cannot use the library', function () {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.media-library.index'))->assertForbidden();
        $this->postJson(route('admin.media-library.uploads.store'), ['name' => 'a.jpg', 'size' => 10, 'fingerprint' => 'a'])->assertForbidden();
    });
});

describe('uploading new images', function () {
    test('an image is uploaded in chunks into the chosen folder under a clean, unique name', function () {
        $this->actingAs(mediaAdmin());
        $bytes = mediaImage(900, 600);
        expect(strlen($bytes))->toBeGreaterThan(4096 * 2);

        sendMedia('Team Photo (1).JPG', $bytes, 'image/jpeg', ['folder' => 'Events/Annual Day'])->assertCreated()->assertJsonPath('file.filename', 'team-photo-1.jpg');
        sendMedia('Team Photo (1).JPG', mediaImage(300, 200), 'image/jpeg', ['folder' => 'Events/Annual Day'])->assertCreated()->assertJsonPath('file.filename', 'team-photo-1-2.jpg');

        Storage::disk('public')->assertExists(['events/annual-day/team-photo-1.jpg', 'events/annual-day/team-photo-1-2.jpg']);
        expect(mediaFile('events/annual-day/team-photo-1.jpg'))->width->toBe(900)->usage_count->toBe(0);
        expect(MediaUpload::count())->toBe(0);
    });

    test('very large photos are scaled down; PNG stays PNG', function () {
        $this->actingAs(mediaAdmin());
        config(['media.max_dimension' => 800]);

        sendMedia('wide.png', mediaImage(1600, 400, 'png'), 'image/png')->assertCreated();

        $file = mediaFile('media/wide.png');
        expect($file)->width->toBe(800)->height->toBe(200);
        expect(getimagesize($file->absolutePath())['mime'])->toBe('image/png');
    });

    test('safe SVGs are accepted and unsafe ones refused', function (string $svg, bool $safe) {
        $this->actingAs(mediaAdmin());
        $response = sendMedia('icon.svg', $svg, 'image/svg+xml');

        $safe ? $response->assertCreated() : $response->assertUnprocessable()->assertJsonValidationErrors('file');
        expect(MediaUpload::count())->toBe(0);
    })->with([
        'plain' => ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4" fill="red"/></svg>', true],
        'internal link' => ['<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><defs><circle id="c" r="4"/></defs><use xlink:href="#c"/></svg>', true],
        'script' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', false],
        'onload' => ['<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>', false],
        'javascript link' => ['<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><text>x</text></a></svg>', false],
        'foreignObject' => ['<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><div xmlns="http://www.w3.org/1999/xhtml">x</div></foreignObject></svg>', false],
        'entities' => ['<!DOCTYPE svg [<!ENTITY x "y">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>', false],
        'external image' => ['<svg xmlns="http://www.w3.org/2000/svg"><image href="https://evil.test/x.png"/></svg>', false],
    ]);

    test('files over the limit, other types and renamed files are refused', function () {
        $this->actingAs(mediaAdmin());

        $this->postJson(route('admin.media-library.uploads.store'), ['name' => 'huge.jpg', 'size' => config('media.max_size') + 1, 'mime' => 'image/jpeg', 'fingerprint' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson(route('admin.media-library.uploads.store'), ['name' => 'doc.pdf', 'size' => 100, 'mime' => 'application/pdf', 'fingerprint' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
        sendMedia('photo.jpg', str_repeat('<?php echo 1; ?>', 700), 'image/jpeg')->assertUnprocessable()->assertJsonValidationErrors('file');

        expect(MediaFile::count())->toBe(0)->and(MediaUpload::count())->toBe(0);
    });

    test('an interrupted upload resumes from the chunks the server has', function () {
        $this->actingAs(mediaAdmin());
        $bytes = mediaImage(800, 800);
        $payload = ['name' => 'a.jpg', 'size' => strlen($bytes), 'mime' => 'image/jpeg', 'fingerprint' => 'a.jpg|1', 'folder' => 'media'];

        $first = $this->postJson(route('admin.media-library.uploads.store'), $payload)->json();
        $this->post(str_replace('__INDEX__', 0, $first['chunk_url']), ['chunk' => UploadedFile::fake()->createWithContent('b', substr($bytes, 0, 4096))], ['Accept' => 'application/json'])->assertOk();

        $again = $this->postJson(route('admin.media-library.uploads.store'), $payload)->json();

        expect($again['id'])->toBe($first['id'])->and($again['received'])->toBe([0]);
    });
});

describe('replacing images', function () {
    beforeEach(function () {
        Storage::disk('public')->put('blogs/cover.jpg', mediaImage(1200, 600));
        scanMedia();
    });

    test('the file is replaced at the same path, keeps its format, and the old one becomes a version', function () {
        $admin = mediaAdmin();
        $this->actingAs($admin);
        $blog = Blog::create(['title' => 'Post', 'slug' => 'post', 'content' => 'x', 'featured_image' => 'blogs/cover.jpg', 'status' => 'active', 'user_id' => $admin->id]);
        $file = mediaFile('blogs/cover.jpg');
        $before = Storage::disk('public')->get('blogs/cover.jpg');
        $urlBefore = media_url('blogs/cover.jpg');

        sendMedia('new-cover.png', mediaImage(800, 400, 'png'), 'image/png', ['media_file_id' => $file->id], ['fit' => 'keep'])
            ->assertOk()
            ->assertJsonPath('file.filename', 'cover.jpg')
            ->assertJsonPath('file.version', 2);

        $absolute = $file->absolutePath();
        expect(Storage::disk('public')->get('blogs/cover.jpg'))->not->toBe($before);
        expect(getimagesize($absolute)['mime'])->toBe('image/jpeg');
        expect($file->fresh())->width->toBe(800)->height->toBe(400)->version->toBe(2);
        expect($blog->fresh()->featured_image)->toBe('blogs/cover.jpg');

        $version = $file->versions()->sole();
        expect(Storage::disk('local')->get($version->backup_path))->toBe($before)->and($version->user_id)->toBe($admin->id);

        expect($urlBefore)->toStartWith(url('storage/blogs/cover.jpg').'?v=');
        expect($file->fresh()->url)->toStartWith(url('storage/blogs/cover.jpg').'?v=')->not->toBe($urlBefore);
    });

    test('a differently shaped image can be fitted to the original size', function () {
        $this->actingAs(mediaAdmin());
        $file = mediaFile('blogs/cover.jpg');

        sendMedia('square.jpg', mediaImage(900, 900), 'image/jpeg', ['media_file_id' => $file->id], ['fit' => 'cover'])->assertOk();

        [$width, $height] = getimagesize($file->absolutePath());
        expect([$width, $height])->toBe([1200, 600]);
    });

    test('an SVG only takes an SVG, an ICO only an ICO', function () {
        $this->actingAs(mediaAdmin());
        Storage::disk('web-root')->put('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>');
        Storage::disk('web-root')->put('favicon.ico', "\x00\x00\x01\x00".str_repeat("\x01", 100));
        scanMedia();

        sendMedia('logo.png', mediaImage(10, 10, 'png'), 'image/png', ['media_file_id' => mediaFile('logo.svg', 'public')->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['file' => 'another SVG']);

        sendMedia('new.ico', "\x00\x00\x01\x00".str_repeat("\x02", 100), 'image/x-icon', ['media_file_id' => mediaFile('favicon.ico', 'public')->id])->assertOk();
        expect(Storage::disk('web-root')->get('favicon.ico'))->toBe("\x00\x00\x01\x00".str_repeat("\x02", 100));
    });

    test('only the newest versions are kept, and restoring swaps back (undoably)', function () {
        $this->actingAs(mediaAdmin());
        config(['media.keep_versions' => 3]);
        $file = mediaFile('blogs/cover.jpg');
        $original = Storage::disk('public')->get('blogs/cover.jpg');

        foreach (range(1, 4) as $i) {
            sendMedia("v{$i}.jpg", mediaImage(600 + $i * 10, 300 + $i * 5), 'image/jpeg', ['media_file_id' => $file->id], ['fit' => 'keep'])->assertOk();
        }

        expect($file->versions()->count())->toBe(3);
        expect(Storage::disk('local')->files("media-versions/{$file->id}"))->toHaveCount(3);

        $oldest = $file->versions()->reorder()->oldest('id')->first();
        $current = Storage::disk('public')->get('blogs/cover.jpg');

        $this->postJson(route('admin.media-library.versions.restore', [$file, $oldest]))->assertOk()->assertJsonPath('file.width', 610);

        expect(Storage::disk('public')->get('blogs/cover.jpg'))->not->toBe($current)->not->toBe($original);
        expect($file->versions()->latest('id')->first()->size)->toBe(strlen($current));
        expect($file->versions()->count())->toBe(3);
    });

    test('replacing a gallery photo also rebuilds its thumbnail and size', function () {
        $this->actingAs(mediaAdmin());
        $gallery = Gallery::create(['title' => 'Day', 'slug' => 'day', 'status' => 'active']);
        Storage::disk('public')->put('gallery/1/photo.webp', mediaImage(1000, 500, 'webp'));
        Storage::disk('public')->put('gallery/1/thumbs/photo.webp', 'old-thumb');
        $item = $gallery->items()->create(['type' => 'image', 'path' => 'gallery/1/photo.webp', 'thumbnail_path' => 'gallery/1/thumbs/photo.webp', 'width' => 1000, 'height' => 500]);
        scanMedia();

        sendMedia('new.jpg', mediaImage(1600, 800), 'image/jpeg', ['media_file_id' => mediaFile('gallery/1/photo.webp')->id], ['fit' => 'keep'])->assertOk();

        expect($item->fresh())->width->toBe(1600)->height->toBe(800);
        expect(getimagesizefromstring(Storage::disk('public')->get('gallery/1/thumbs/photo.webp'))[0])->toBe(480);
    });
});

describe('deleting', function () {
    test('only unused images in storage can be deleted', function () {
        $admin = mediaAdmin();
        $this->actingAs($admin);
        Storage::disk('public')->put('orphan.jpg', mediaImage(50, 50));
        Storage::disk('public')->put('blogs/used.jpg', mediaImage(50, 50));
        Storage::disk('web-root')->put('images/logo.png', mediaImage(50, 50, 'png'));
        Blog::create(['title' => 'Post', 'slug' => 'p', 'content' => 'x', 'featured_image' => 'blogs/used.jpg', 'status' => 'active', 'user_id' => $admin->id]);
        scanMedia();

        $this->deleteJson(route('admin.media-library.destroy', mediaFile('blogs/used.jpg')))->assertUnprocessable()->assertJsonValidationErrors(['file' => 'used in 1 place']);
        $this->deleteJson(route('admin.media-library.destroy', mediaFile('images/logo.png', 'public')))->assertUnprocessable();

        $orphan = mediaFile('orphan.jpg');
        $this->deleteJson(route('admin.media-library.destroy', $orphan))->assertOk();

        Storage::disk('public')->assertMissing('orphan.jpg');
        Storage::disk('public')->assertExists('blogs/used.jpg');
        Storage::disk('web-root')->assertExists('images/logo.png');
        expect(MediaFile::find($orphan->id))->toBeNull();
    });

    test('a file that became used since the last scan is protected', function () {
        $admin = mediaAdmin();
        $this->actingAs($admin);
        Storage::disk('public')->put('blogs/new.jpg', mediaImage(50, 50));
        scanMedia();
        Blog::create(['title' => 'Fresh', 'slug' => 'f', 'content' => 'x', 'featured_image' => 'blogs/new.jpg', 'status' => 'active', 'user_id' => $admin->id]);

        $this->deleteJson(route('admin.media-library.destroy', mediaFile('blogs/new.jpg')))->assertUnprocessable();
        Storage::disk('public')->assertExists('blogs/new.jpg');
    });
});

test('media_url adds a version that changes when the file changes', function () {
    Storage::disk('public')->put('a.jpg', 'one');
    touch(Storage::disk('public')->path('a.jpg'), 1_700_000_000);

    expect(media_url('a.jpg'))->toBe(url('storage/a.jpg').'?v=1700000000');
    expect(media_url('missing.jpg'))->toBe(url('storage/missing.jpg'));
    expect(media_url(null))->toBeNull();
});
