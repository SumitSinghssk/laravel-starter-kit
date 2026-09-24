<?php

use App\Models\Blog;
use App\Models\Enquiry;
use App\Models\MediaFile;
use App\Models\Page;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Media\MediaScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

function trashAdmin(array $modules = ['blogs', 'pages', 'blog-categories', 'testimonials', 'users', 'enquiries']): User
{
    $role = Role::findOrCreate('super admin', 'web');
    $role->givePermissionTo(Permission::where('name', 'like', 'admin.trash.%')->get());

    foreach ($modules as $module) {
        $role->givePermissionTo(Permission::findOrCreate("admin.{$module}.view", 'web'));
    }

    return tap(User::factory()->create())->assignRole($role);
}

function trashedBlog(User $author, array $attributes = []): Blog
{
    $blog = Blog::create(['title' => 'Old post', 'slug' => 'old-post', 'content' => 'x', 'status' => 'active', 'user_id' => $author->id, ...$attributes]);
    $blog->delete();

    return $blog;
}

test('the trash lists deleted items of every type, with tabs and search', function () {
    $admin = trashAdmin();
    trashedBlog($admin, ['title' => 'Launch notes']);
    tap(Page::create(['title' => 'Old about page', 'slug' => 'about', 'content' => 'x', 'status' => 'active', 'user_id' => $admin->id]))->delete();
    tap(Testimonial::create(['name' => 'Priya Nair', 'quote' => 'Great work with our team.', 'status' => 'active']))->delete();
    tap(Enquiry::factory()->create(['data' => ['name' => 'Ravi Kumar', 'email' => 'ravi@example.com']]))->delete();
    Blog::create(['title' => 'Live post', 'slug' => 'live', 'content' => 'x', 'status' => 'active', 'user_id' => $admin->id]);

    $this->actingAs($admin);

    $this->get(route('admin.trash.index'))->assertOk()
        ->assertSee('Launch notes')->assertSee('/old-post')
        ->assertSee('Old about page')->assertSee('Priya Nair')->assertSee('Ravi Kumar')
        ->assertDontSee('Live post')
        ->assertSee('Deleted for good in 30 days');

    $this->get(route('admin.trash.index', ['type' => 'pages']))->assertSee('Old about page')->assertDontSee('Launch notes');
    $this->get(route('admin.trash.index', ['search' => 'ravi']))->assertSee('Ravi Kumar')->assertDontSee('Priya Nair');
});

test('restoring puts an item back with its web address', function () {
    $admin = trashAdmin();
    $blog = trashedBlog($admin);
    expect($blog->fresh()->slug)->toBeNull();

    $this->actingAs($admin)
        ->post(route('admin.trash.restore'), ['items' => ["blogs:{$blog->id}"]])
        ->assertSessionHas('success', '1 item restored.');

    expect($blog->fresh())->trashed()->toBeFalse()->slug->toBe('old-post');
});

test('if the address was taken meanwhile, the restored item gets a free one and a note', function () {
    $admin = trashAdmin();
    $blog = trashedBlog($admin);
    Blog::create(['title' => 'New post', 'slug' => 'old-post', 'content' => 'x', 'status' => 'active', 'user_id' => $admin->id]);

    $this->actingAs($admin)
        ->post(route('admin.trash.restore'), ['items' => ["blogs:{$blog->id}"]])
        ->assertSessionHas('info', fn ($note) => str_contains($note, 'old-post-restored'));

    expect($blog->fresh()->slug)->toBe('old-post-restored');
});

test('deleting permanently removes the record and its image', function () {
    $admin = trashAdmin();
    Storage::disk('public')->put('testimonials/priya.jpg', 'img');
    $testimonial = tap(Testimonial::create(['name' => 'Priya', 'quote' => 'Great work with our team.', 'photo' => 'testimonials/priya.jpg', 'status' => 'active']))->delete();

    $this->actingAs($admin)
        ->delete(route('admin.trash.destroy'), ['items' => ["testimonials:{$testimonial->id}"]])
        ->assertSessionHas('success', '1 item permanently deleted.');

    expect(Testimonial::withTrashed()->find($testimonial->id))->toBeNull();
    Storage::disk('public')->assertMissing('testimonials/priya.jpg');
});

test('a user who wrote blog posts cannot be deleted permanently (it would delete the posts)', function () {
    $admin = trashAdmin();
    $author = User::factory()->create(['name' => 'Asha']);
    Blog::create(['title' => 'By Asha', 'slug' => 'by-asha', 'content' => 'x', 'status' => 'active', 'user_id' => $author->id]);
    $author->delete();

    $this->actingAs($admin)
        ->delete(route('admin.trash.destroy'), ['items' => ["users:{$author->id}"]])
        ->assertSessionHas('error', fn ($note) => str_contains($note, 'Asha still has 1 blog post'));

    expect(User::withTrashed()->find($author->id))->not->toBeNull();
    expect(Blog::where('slug', 'by-asha')->exists())->toBeTrue();
});

test('only a super admin can permanently delete a super admin', function () {
    $role = Role::findOrCreate('editor', 'web');
    $role->givePermissionTo([Permission::findOrCreate('admin.trash.view', 'web'), Permission::findOrCreate('admin.trash.delete', 'web'), Permission::findOrCreate('admin.users.view', 'web')]);
    $editor = tap(User::factory()->create())->assignRole($role);
    $boss = tap(User::factory()->create())->assignRole(Role::findOrCreate('super admin', 'web'));
    $boss->delete();

    $this->actingAs($editor)->delete(route('admin.trash.destroy'), ['items' => ["users:{$boss->id}"]])->assertSessionHas('error');

    expect(User::withTrashed()->find($boss->id))->not->toBeNull();
});

test('emptying the trash can be limited to one type', function () {
    $admin = trashAdmin();
    $blog = trashedBlog($admin);
    $testimonial = tap(Testimonial::create(['name' => 'Priya', 'quote' => 'Great work with our team.', 'status' => 'active']))->delete();

    $this->actingAs($admin)->delete(route('admin.trash.empty'), ['type' => 'testimonials'])->assertRedirect(route('admin.trash.index', ['type' => 'testimonials']));

    expect(Testimonial::withTrashed()->find($testimonial->id))->toBeNull();
    expect(Blog::withTrashed()->find($blog->id))->not->toBeNull();
});

test('items older than the retention period are purged when the trash is opened', function () {
    $admin = trashAdmin();
    $old = trashedBlog($admin, ['slug' => 'ancient']);
    $old->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();
    $recent = trashedBlog($admin, ['slug' => 'recent']);

    $this->actingAs($admin)->get(route('admin.trash.index'))->assertOk();

    expect(Blog::withTrashed()->find($old->id))->toBeNull();
    expect(Blog::withTrashed()->find($recent->id))->not->toBeNull();
});

test('the purge command does the same', function () {
    $admin = trashAdmin();
    $old = trashedBlog($admin);
    $old->forceFill(['deleted_at' => now()->subDays(40)])->saveQuietly();

    $this->artisan('trash:purge')->assertSuccessful();

    expect(Blog::withTrashed()->find($old->id))->toBeNull();
});

test('people only see and act on types they may view', function () {
    $admin = trashAdmin(['pages']);
    $blog = trashedBlog(User::factory()->create(), ['title' => 'Hidden post']);

    $this->actingAs($admin)->get(route('admin.trash.index'))->assertOk()->assertDontSee('Hidden post');
    $this->delete(route('admin.trash.destroy'), ['items' => ["blogs:{$blog->id}"]]);

    expect(Blog::withTrashed()->find($blog->id))->not->toBeNull();
});

test('without the trash permission the page is forbidden', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.trash.index'))->assertForbidden();
});

test('list pages link to their trash', function () {
    $admin = trashAdmin();
    $admin->roles->first()->givePermissionTo(Permission::findOrCreate('admin.testimonials.view', 'web'));
    tap(Testimonial::create(['name' => 'Priya', 'quote' => 'Great work with our team.', 'status' => 'active']))->delete();

    $this->actingAs($admin)->get(route('admin.testimonials.index'))->assertSee('Trash (1)')->assertSee(route('admin.trash.index', ['type' => 'testimonials']), false);
});

test('images of trashed items still count as used in the media library', function () {
    $admin = trashAdmin();
    config(['media.roots' => ['storage' => Storage::disk('public')->path(''), 'public' => Storage::disk('public')->path('__none__')]]);
    Storage::disk('public')->put('blogs/cover.jpg', 'img');
    trashedBlog($admin, ['featured_image' => 'blogs/cover.jpg', 'title' => 'Gone post']);

    app(MediaScanner::class)->scan();

    $file = MediaFile::where('path', 'blogs/cover.jpg')->sole();
    expect($file->usage_count)->toBe(1)->and($file->usages->first()->title)->toBe('Gone post (in trash)');
});
