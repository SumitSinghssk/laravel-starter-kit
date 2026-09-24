<?php

use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function notFoundAdmin(): User
{
    $role = Role::findOrCreate('super admin', 'web');
    $role->givePermissionTo(Permission::where('name', 'like', 'admin.not-found.%')->orWhere('name', 'like', 'admin.redirects.%')->get());

    return tap(User::factory()->create())->assignRole($role);
}

describe('recording', function () {
    test('missing pages are logged once per URL with a hit counter and the referrer', function () {
        $this->get('/Old-Page/?utm=1', ['Referer' => 'https://google.com/search?q=x'])->assertNotFound();
        $this->get('/old-page')->assertNotFound();

        $log = NotFoundLog::sole();
        expect($log)->path->toBe('/old-page')->hits->toBe(2)->last_referrer->toBeNull();
        expect($log->first_seen_at)->not->toBeNull();
    });

    test('the referrer tells where the broken link is', function () {
        $this->get('/missing', ['Referer' => url('/blog/some-post')])->assertNotFound();

        $log = NotFoundLog::sole();
        expect($log->isInternalReferrer())->toBeTrue()->and($log->referrer_label)->toBe('/blog/some-post');
    });

    test('bot probes, admin URLs, other methods and working pages are not logged', function () {
        $this->get('/wp-login.php')->assertNotFound();
        $this->get('/.env')->assertNotFound();
        $this->get('/wp-admin/setup-config.php')->assertNotFound();
        $this->get('/admin/nope')->assertStatus(404);
        $this->post('/missing-form');
        $this->get('/')->assertOk();

        expect(NotFoundLog::count())->toBe(0);
    });

    test('URLs with a redirect are redirected instead of logged', function () {
        Redirect::create(['source_path' => '/old', 'target_url' => '/', 'status_code' => 301, 'status' => 'active']);

        $this->get('/old')->assertRedirect();

        expect(NotFoundLog::count())->toBe(0);
    });
});

describe('admin page', function () {
    beforeEach(function () {
        NotFoundLog::create(['path' => '/popular-old', 'hits' => 40, 'first_seen_at' => now()->subDays(5), 'last_seen_at' => now()->subHour(), 'last_referrer' => 'https://news.example.com/article']);
        NotFoundLog::create(['path' => '/typo', 'hits' => 2, 'first_seen_at' => now()->subDay(), 'last_seen_at' => now()->subMinutes(5)]);
        NotFoundLog::create(['path' => '/noise', 'hits' => 1, 'ignored' => true, 'first_seen_at' => now(), 'last_seen_at' => now()]);
    });

    test('lists open 404s with tabs, sorting and search', function () {
        $this->actingAs(notFoundAdmin());

        $this->get(route('admin.not-found.index'))->assertOk()
            ->assertSee('/popular-old')->assertSee('/typo')->assertDontSee('/noise')
            ->assertSee('news.example.com')->assertSee('Create redirect');

        $this->get(route('admin.not-found.index', ['sort' => 'hits']))->assertSeeInOrder(['/popular-old', '/typo']);
        $this->get(route('admin.not-found.index', ['sort' => 'recent']))->assertSeeInOrder(['/typo', '/popular-old']);
        $this->get(route('admin.not-found.index', ['status' => 'ignored']))->assertSee('/noise')->assertDontSee('/typo');
        $this->get(route('admin.not-found.index', ['search' => 'news.example']))->assertSee('/popular-old')->assertDontSee('/typo');
    });

    test('"Create redirect" makes a redirect and moves the URL to the Redirected list', function () {
        $this->actingAs(notFoundAdmin());

        $this->post(route('admin.not-found.redirect'), ['source_path' => '/popular-old', 'target_url' => '/new-home', 'status_code' => 301, 'status' => 'active'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Redirect created: /popular-old → /new-home');

        expect(Redirect::sole())->source_path->toBe('/popular-old');

        $this->get(route('admin.not-found.index'))->assertDontSee('href="'.url('/popular-old').'"', false);
        $this->get(route('admin.not-found.index', ['status' => 'redirected']))->assertSee('/popular-old')->assertSee('Redirected');

        $this->get('/popular-old')->assertRedirect(url('/new-home'));
    });

    test('redirect rules still apply (e.g. no loops, no admin URLs)', function () {
        $this->actingAs(notFoundAdmin());

        $this->post(route('admin.not-found.redirect'), ['source_path' => '/typo', 'target_url' => '/typo', 'status_code' => 301, 'status' => 'active'])
            ->assertSessionHasErrors('target_url');
    });

    test('entries can be ignored, restored, removed and cleared', function () {
        $this->actingAs(notFoundAdmin());
        $typo = NotFoundLog::where('path', '/typo')->sole();
        $noise = NotFoundLog::where('path', '/noise')->sole();

        $this->post(route('admin.not-found.ignore'), ['ids' => [$typo->id], 'ignore' => 1])->assertSessionHas('success');
        expect($typo->fresh()->ignored)->toBeTrue();

        $this->post(route('admin.not-found.ignore'), ['ids' => [$noise->id], 'ignore' => 0]);
        expect($noise->fresh()->ignored)->toBeFalse();

        $this->delete(route('admin.not-found.destroy'), ['ids' => [$noise->id]]);
        expect(NotFoundLog::find($noise->id))->toBeNull();

        $this->delete(route('admin.not-found.clear', ['status' => 'ignored']))->assertRedirect(route('admin.not-found.index', ['status' => 'ignored']));
        expect(NotFoundLog::pluck('path')->all())->toBe(['/popular-old']);
    });

    test('old entries are pruned', function () {
        NotFoundLog::where('path', '/typo')->update(['last_seen_at' => now()->subDays(91)]);
        $this->actingAs(notFoundAdmin())->get(route('admin.not-found.index'))->assertOk();

        expect(NotFoundLog::where('path', '/typo')->exists())->toBeFalse();
    });

    test('people without the permission cannot see the list', function () {
        $this->actingAs(User::factory()->create())->get(route('admin.not-found.index'))->assertForbidden();
    });
});
