<?php

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Enquiry;
use App\Models\Import;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

function transferAdmin(array $permissions = []): User
{
    $role = Role::findOrCreate('super admin', 'web');

    foreach (['blogs', 'blog-categories', 'pages', 'testimonials', 'redirects', 'seo', 'users', 'enquiries', 'not-found'] as $module) {
        foreach (['view', 'create', 'delete', 'toogle-status'] as $action) {
            $role->givePermissionTo(Permission::findOrCreate("admin.{$module}.{$action}", 'web'));
        }
    }
    $role->givePermissionTo(Permission::findOrCreate('admin.enquiries.edit', 'web'));

    return tap(User::factory()->create())->assignRole($role);
}

function csvFile(string $name, array $rows): UploadedFile
{
    $handle = fopen('php://temp', 'r+');
    foreach ($rows as $row) {
        fputcsv($handle, $row, ',', '"', '');
    }
    rewind($handle);

    return UploadedFile::fake()->createWithContent($name, stream_get_contents($handle));
}

function makeBlog(User $author, array $attributes = []): Blog
{
    return Blog::create(['title' => 'A post', 'slug' => 'a-post', 'content' => 'x', 'status' => 'active', 'user_id' => $author->id, ...$attributes]);
}

function pngBytes(): string
{
    $image = imagecreatetruecolor(4, 4);
    ob_start();
    imagepng($image);

    return ob_get_clean();
}

test('a list exports as CSV with every column, and formulas are neutralised', function () {
    $admin = transferAdmin();
    $category = BlogCategory::create(['name' => 'Guides', 'slug' => 'guides', 'status' => 'active']);
    makeBlog($admin, ['title' => '=HYPERLINK("http://evil")', 'slug' => 'evil'])->categories()->attach($category);
    makeBlog($admin, ['title' => 'Hello, world', 'slug' => 'hello']);

    $csv = $this->actingAs($admin)->get(route('admin.transfer.export', 'blogs'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    $lines = array_map(fn ($line) => str_getcsv($line, ',', '"', ''), preg_split('/\r?\n/', trim(ltrim($csv, "\u{FEFF}"))));

    expect($lines[0])->toBe(['title', 'slug', 'excerpt', 'content', 'status', 'published_at', 'categories', 'featured_image', 'author_email'])
        ->and(collect($lines)->pluck(0))->toContain('Hello, world')
        ->and(collect($lines)->pluck(0))->toContain('\'=HYPERLINK("http://evil")')
        ->and(collect($lines)->firstWhere(1, 'evil')[6])->toBe('Guides');
});

test('export needs the module view permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.transfer.export', 'blogs'))->assertForbidden();
    $this->actingAs(transferAdmin())->get(route('admin.transfer.export', 'nope'))->assertNotFound();
});

test('export-only lists have no import page', function () {
    $this->actingAs(transferAdmin())->get(route('admin.transfer.create', 'users'))->assertNotFound();
    $this->actingAs(transferAdmin())->get(route('admin.transfer.create', 'enquiries'))->assertNotFound();
});

test('the preview marks new, existing, repeated and invalid rows and saves nothing', function () {
    $admin = transferAdmin();
    makeBlog($admin, ['title' => 'Already here', 'slug' => 'already-here']);

    $response = $this->actingAs($admin)->post(route('admin.transfer.store', 'blogs'), [
        'file' => csvFile('posts.csv', [
            ['title', 'content', 'categories', 'color'],
            ['Brand new post', 'Body', 'Missing category', 'red'],
            ['Already here', 'Body', '', ''],
            ['Brand new post', 'Again', '', ''],
            ['', 'No title', '', ''],
        ]),
    ]);

    $import = Import::sole();
    $response->assertRedirect(route('admin.transfer.imports.show', $import));

    expect($import->status)->toBe(Import::READY)
        ->and($import->counts)->toMatchArray(['new' => 1, 'exists' => 1, 'duplicate' => 1, 'invalid' => 1, 'unknown_columns' => ['color']])
        ->and(Blog::count())->toBe(1);

    $rows = collect($import->rows())->keyBy('line');
    expect($rows[2]['status'])->toBe('new')
        ->and($rows[2]['warnings'][0])->toContain('Missing category')
        ->and($rows[3]['status'])->toBe('exists')
        ->and($rows[3]['messages'][0])->toContain('It will not be imported')
        ->and($rows[4]['messages'][0])->toContain('Same as row 2')
        ->and($rows[5]['status'])->toBe('invalid');

    $this->get(route('admin.transfer.imports.show', $import))->assertOk()
        ->assertSee('Check before importing')->assertSee('Already exists')->assertSee('Repeated in file')
        ->assertSee('Import 1 row')->assertSee('color');

    $this->get(route('admin.transfer.imports.show', [$import, 'show' => 'exists']))->assertSee('Already here')->assertDontSee('Brand new post');
});

test('a file without the required columns is refused', function () {
    $this->actingAs(transferAdmin())
        ->post(route('admin.transfer.store', 'blogs'), ['file' => csvFile('posts.csv', [['title'], ['Only a title']])])
        ->assertSessionHasErrors(['file' => 'The file is missing the required column: content. Download the template to see the expected columns.']);

    expect(Import::count())->toBe(0);
});

test('importing creates only the new rows, downloads images and links categories', function () {
    Http::fake(['93.184.216.34/*' => Http::response(pngBytes(), 200, ['Content-Type' => 'image/png'])]);
    $admin = transferAdmin();
    BlogCategory::create(['name' => 'Guides', 'slug' => 'guides', 'status' => 'active']);
    makeBlog($admin, ['title' => 'Old', 'slug' => 'old', 'content' => 'original']);

    $this->actingAs($admin)->post(route('admin.transfer.store', 'blogs'), [
        'file' => csvFile('posts.csv', [
            ['title', 'slug', 'content', 'status', 'categories', 'featured_image'],
            ['First', '', '<p>One</p>', 'inactive', 'guides', 'http://93.184.216.34/cover.png'],
            ['Old', 'old', 'changed', '', '', ''],
        ]),
    ]);
    $import = Import::sole();

    $this->postJson(route('admin.transfer.imports.step', $import))->assertOk()
        ->assertJson(['status' => 'completed', 'percent' => 100, 'created' => 1, 'failed' => 0, 'skipped' => 1]);

    $blog = Blog::where('slug', 'first')->sole();
    expect($blog->status->value)->toBe('inactive')
        ->and($blog->user_id)->toBe($admin->id)
        ->and($blog->categories->pluck('slug')->all())->toBe(['guides'])
        ->and($blog->featured_image)->toStartWith('blogs/')
        ->and(Storage::disk('public')->exists($blog->featured_image))->toBeTrue()
        ->and(Blog::where('slug', 'old')->value('content'))->toBe('original');

    $this->get(route('admin.transfer.imports.show', $import))->assertSee('Import finished')->assertSee(route('admin.blogs.edit', $blog), false);

    $this->postJson(route('admin.transfer.imports.step', $import))->assertJson(['created' => 1]);
    expect(Blog::count())->toBe(2);
});

test('a row added since the preview is skipped, not imported twice', function () {
    $admin = transferAdmin();
    $this->actingAs($admin)->post(route('admin.transfer.store', 'blogs'), [
        'file' => csvFile('posts.csv', [['title', 'content'], ['Race', 'x']]),
    ]);
    makeBlog($admin, ['title' => 'Race', 'slug' => 'race']);

    $this->postJson(route('admin.transfer.imports.step', Import::sole()))->assertJson(['created' => 0, 'skipped' => 1]);
    expect(Blog::count())->toBe(1);
});

test('images are never fetched from private addresses, and a failed row leaves nothing behind', function () {
    Http::fake();
    $admin = transferAdmin();

    $this->actingAs($admin)->post(route('admin.transfer.store', 'blogs'), [
        'file' => csvFile('posts.csv', [
            ['title', 'content', 'featured_image'],
            ['Internal', 'x', 'http://127.0.0.1/admin.png'],
            ['Metadata', 'x', 'http://169.254.169.254/latest'],
            ['Not a url', 'x', 'file:///etc/passwd'],
        ]),
    ]);
    $import = Import::sole();

    $this->postJson(route('admin.transfer.imports.step', $import))->assertJson(['created' => 0, 'failed' => 3]);

    Http::assertNothingSent();
    expect(Blog::count())->toBe(0)
        ->and(collect($import->fresh()->rows())->pluck('messages.0')->implode(' '))->toContain("can't be downloaded from private or local addresses");
});

test('a file that is not an image is refused', function () {
    Http::fake(['93.184.216.34/*' => Http::response('<html>not an image</html>', 200, ['Content-Type' => 'image/png'])]);
    $admin = transferAdmin();

    $this->actingAs($admin)->post(route('admin.transfer.store', 'blogs'), [
        'file' => csvFile('posts.csv', [['title', 'content', 'featured_image'], ['Fake', 'x', 'http://93.184.216.34/x.png']]),
    ]);
    $this->postJson(route('admin.transfer.imports.step', Import::sole()))->assertJson(['failed' => 1]);

    expect(Blog::count())->toBe(0)->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('categories can use a parent from an earlier row of the same file', function () {
    $admin = transferAdmin();

    $this->actingAs($admin)->post(route('admin.transfer.store', 'blog-categories'), [
        'file' => csvFile('categories.csv', [
            ['name', 'parent'],
            ['Tech', ''],
            ['Laravel', 'Tech'],
            ['Orphan', 'Nowhere'],
        ]),
    ]);
    $import = Import::sole();
    expect($import->counts)->toMatchArray(['new' => 2, 'invalid' => 1]);

    $this->postJson(route('admin.transfer.imports.step', $import))->assertJson(['created' => 2]);
    expect(BlogCategory::where('slug', 'laravel')->sole()->parent->slug)->toBe('tech');
});

test('redirect imports skip existing old URLs and refuse loops', function () {
    $admin = transferAdmin();
    Redirect::create(['source_path' => '/old', 'target_url' => '/new', 'status_code' => 301, 'status' => 'active']);

    $this->actingAs($admin)->post(route('admin.transfer.store', 'redirects'), [
        'file' => csvFile('redirects.csv', [
            ['old_url', 'new_url', 'type'],
            ['/old', '/elsewhere', '301'],
            ['/new', '/old', '301'],
            ['/fresh', '/landing', '302'],
        ]),
    ]);

    expect(Import::sole()->counts)->toMatchArray(['new' => 1, 'exists' => 1, 'invalid' => 1]);
});

test('imports belong to the person who uploaded them', function () {
    $this->actingAs(transferAdmin())->post(route('admin.transfer.store', 'blogs'), [
        'file' => csvFile('posts.csv', [['title', 'content'], ['Mine', 'x']]),
    ]);
    $import = Import::sole();

    $this->actingAs(transferAdmin())->get(route('admin.transfer.imports.show', $import))->assertNotFound();
    $this->postJson(route('admin.transfer.imports.step', $import))->assertNotFound();
});

test('discarding an import removes it and its stored rows', function () {
    $this->actingAs(transferAdmin())->post(route('admin.transfer.store', 'blogs'), [
        'file' => csvFile('posts.csv', [['title', 'content'], ['Mine', 'x']]),
    ]);
    $import = Import::sole();
    Storage::disk('local')->assertExists($import->rowsPath());

    $this->delete(route('admin.transfer.imports.destroy', $import))->assertRedirect(route('admin.transfer.create', 'blogs'));

    expect(Import::count())->toBe(0);
    Storage::disk('local')->assertMissing($import->rowsPath());
});

test('bulk actions activate, deactivate and move rows to the trash', function () {
    $admin = transferAdmin();
    $a = makeBlog($admin, ['slug' => 'a', 'status' => 'inactive']);
    $b = makeBlog($admin, ['slug' => 'b', 'status' => 'inactive']);
    $c = makeBlog($admin, ['slug' => 'c']);

    $this->actingAs($admin)->from(route('admin.blogs.index'))
        ->post(route('admin.bulk', 'blogs'), ['action' => 'activate', 'ids' => [$a->id, $b->id]])
        ->assertRedirect(route('admin.blogs.index'))
        ->assertSessionHas('success', '2 posts activated.');
    expect(Blog::whereIn('id', [$a->id, $b->id])->pluck('status')->map->value->unique()->all())->toBe(['active']);

    $this->post(route('admin.bulk', 'blogs'), ['action' => 'delete', 'ids' => [$a->id, $c->id]])
        ->assertSessionHas('success', '2 posts moved to the Trash.');
    expect(Blog::count())->toBe(1)->and(Blog::onlyTrashed()->count())->toBe(2);
});

test('bulk actions need the permission for that action', function () {
    $admin = transferAdmin();
    $blog = makeBlog($admin);
    $viewer = tap(User::factory()->create())->givePermissionTo(Permission::findOrCreate('admin.blogs.view', 'web'));

    $this->actingAs($viewer)->post(route('admin.bulk', 'blogs'), ['action' => 'delete', 'ids' => [$blog->id]])->assertForbidden();
    $this->actingAs($admin)->post(route('admin.bulk', 'blogs'), ['action' => 'mark-closed', 'ids' => [$blog->id]])->assertSessionHasErrors('action');
    $this->actingAs($admin)->post(route('admin.bulk', 'galleries'), ['action' => 'delete', 'ids' => [1]])->assertNotFound();

    expect(Blog::count())->toBe(1);
});

test('bulk user actions skip your own account', function () {
    $admin = transferAdmin();
    $other = User::factory()->create(['status' => 'active']);

    $this->actingAs($admin)->post(route('admin.bulk', 'users'), ['action' => 'deactivate', 'ids' => [$admin->id, $other->id]])
        ->assertSessionHas('success', '1 user deactivated. Skipped: your own account.');

    expect($other->fresh()->status->value)->toBe('inactive')->and($admin->fresh()->status->value)->toBe('active');
});

test('bulk enquiry status changes mark them seen', function () {
    $admin = transferAdmin();
    $enquiries = Enquiry::factory()->count(2)->create(['status' => 'new', 'seen_at' => null]);

    $this->actingAs($admin)->post(route('admin.bulk', 'enquiries'), ['action' => 'mark-closed', 'ids' => $enquiries->pluck('id')->all()])
        ->assertSessionHas('success', '2 enquiries marked as Closed.');

    $enquiries->each(fn ($enquiry) => expect($enquiry->fresh())->status->toBe('closed')->seen_by->toBe($admin->id));
});

test('list pages show the export, import and selection controls', function () {
    $admin = transferAdmin();
    makeBlog($admin);

    $this->actingAs($admin)->get(route('admin.blogs.index'))->assertOk()
        ->assertSee(route('admin.transfer.export', 'blogs'))
        ->assertSee(route('admin.transfer.create', 'blogs'))
        ->assertSee(route('admin.bulk', 'blogs'))
        ->assertSee('Move to Trash');
});
