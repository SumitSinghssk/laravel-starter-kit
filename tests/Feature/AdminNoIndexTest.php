<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('every admin response tells search engines not to index it', function (string $path) {
    $this->get($path)->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
})->with([
    'login page' => ['/admin/login'],
    'redirect to login' => ['/admin/dashboard'],
    'theme preview while logged out' => ['/admin/appearance/preview'],
    'unknown admin page' => ['/admin/does-not-exist'],
]);

test('logged-in admin pages and json are noindex too', function () {
    $admin = tap(User::factory()->create())->givePermissionTo(Permission::findOrCreate('admin.appearance.view', 'web'));

    $this->actingAs($admin)->get('/admin/appearance/preview')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $this->actingAs($admin)->postJson('/admin/appearance/check', [])->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
});

test('the public website stays indexable', function () {
    $this->get('/')->assertOk()->assertHeaderMissing('X-Robots-Tag');
});

test('the theme preview needs a logged-in admin with access', function () {
    $this->get('/admin/appearance/preview')->assertRedirect(route('admin.login'));

    $this->actingAs(User::factory()->create())->get('/admin/appearance/preview')->assertForbidden();
});
