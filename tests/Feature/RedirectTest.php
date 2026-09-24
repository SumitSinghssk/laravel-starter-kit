<?php

use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function redirectAdmin(): User
{
    $role = Role::findOrCreate('super admin', 'web');
    $role->givePermissionTo(Permission::where('name', 'like', 'admin.redirects.%')->get());

    return tap(User::factory()->create())->assignRole($role);
}

function makeRedirect(array $attributes = []): Redirect
{
    return Redirect::create([
        'source_path' => '/old-page',
        'target_url' => '/new-page',
        'status_code' => 301,
        'status' => 'active',
        ...$attributes,
    ]);
}

describe('website', function () {
    test('an old URL redirects with its status code and counts the hit', function () {
        $redirect = makeRedirect();

        $this->get('/old-page')->assertRedirect(url('/new-page'))->assertStatus(301);

        expect($redirect->fresh())->hits->toBe(1)->last_hit_at->not->toBeNull();
    });

    test('matching ignores letter case and a trailing slash, and keeps the query string', function () {
        makeRedirect(['status_code' => 302]);

        $this->get('/Old-Page/?utm_source=mail')->assertRedirect(url('/new-page').'?utm_source=mail')->assertStatus(302);
    });

    test('a target with its own query string is used as is', function () {
        makeRedirect(['target_url' => '/new-page?ref=old']);

        $this->get('/old-page?x=1')->assertRedirect(url('/new-page?ref=old'));
    });

    test('external targets are followed', function () {
        makeRedirect(['target_url' => 'https://example.com/page']);

        $this->get('/old-page')->assertRedirect('https://example.com/page');
    });

    test('inactive redirects are ignored', function () {
        makeRedirect(['status' => 'inactive']);

        $this->get('/old-page')->assertNotFound();
    });

    test('changes apply immediately (cache is cleared on save)', function () {
        $redirect = makeRedirect();
        $this->get('/old-page')->assertRedirect(url('/new-page'));

        $redirect->update(['target_url' => '/newer-page']);

        $this->get('/old-page')->assertRedirect(url('/newer-page'));
    });

    test('admin URLs and non-GET requests are never redirected', function () {
        DB::table('redirects')->insert(['source_path' => '/admin/login', 'target_url' => '/x', 'status_code' => 301, 'status' => 'active']);
        makeRedirect();

        $this->get('/admin/login')->assertOk();
        $this->post('/old-page')->assertStatus(404);
    });
});

describe('admin', function () {
    test('an admin can create a redirect and the paths are normalised', function () {
        $this->actingAs(redirectAdmin())
            ->post(route('admin.redirects.store'), [
                'source_path' => url('/Blog/Old-Post/').'?page=2',
                'target_url' => 'blog/new-post',
                'status_code' => 301,
                'status' => 'active',
            ])
            ->assertSessionHasNoErrors();

        expect(Redirect::sole())->source_path->toBe('/blog/old-post')->target_url->toBe('/blog/new-post');
    });

    test('loops, admin paths, duplicates and unsafe targets are rejected', function (array $input, string $field) {
        makeRedirect(['source_path' => '/b', 'target_url' => '/c']);
        makeRedirect(['source_path' => '/c', 'target_url' => '/a']);

        $this->actingAs(redirectAdmin())
            ->post(route('admin.redirects.store'), [
                'status_code' => 301,
                'status' => 'active',
                ...$input,
            ])
            ->assertSessionHasErrors($field);
    })->with([
        'points to itself' => [['source_path' => '/a', 'target_url' => '/A/'], 'target_url'],
        'loop through other redirects' => [['source_path' => '/a', 'target_url' => '/b'], 'target_url'],
        'admin panel' => [['source_path' => '/admin/users', 'target_url' => '/x'], 'source_path'],
        'home page' => [['source_path' => '/', 'target_url' => '/x'], 'source_path'],
        'duplicate' => [['source_path' => '/B/', 'target_url' => '/x'], 'source_path'],
        'javascript url' => [['source_path' => '/a', 'target_url' => 'javascript:alert(1)'], 'target_url'],
        'protocol-relative url' => [['source_path' => '/a', 'target_url' => '//evil.test'], 'target_url'],
        'unknown type' => [['source_path' => '/a', 'target_url' => '/x', 'status_code' => 303], 'status_code'],
    ]);

    test('editing a redirect keeps its own path valid', function () {
        $redirect = makeRedirect();

        $this->actingAs(redirectAdmin())
            ->put(route('admin.redirects.update', $redirect), [
                'source_path' => '/old-page',
                'target_url' => '/another-page',
                'status_code' => 302,
                'status' => 'active',
            ])
            ->assertSessionHasNoErrors();

        expect($redirect->fresh())->target_url->toBe('/another-page')->status_code->toBe(302);
    });

    test('the admin pages render', function () {
        $redirect = makeRedirect(['note' => 'Moved during redesign']);
        $this->actingAs(redirectAdmin());

        $this->get(route('admin.redirects.index'))->assertOk()->assertSee('/old-page')->assertSee('Moved during redesign');
        $this->get(route('admin.redirects.index', ['search' => 'old', 'type' => 301, 'status' => 'active']))->assertOk()->assertSee('/old-page');
        $this->get(route('admin.redirects.create'))->assertOk();
        $this->get(route('admin.redirects.edit', $redirect))->assertOk();
    });

    test('users without the permission cannot manage redirects', function () {
        $this->actingAs(User::factory()->create())->get(route('admin.redirects.index'))->assertForbidden();
    });
});
