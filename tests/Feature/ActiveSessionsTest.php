<?php

use App\Models\User;
use App\Services\ActiveSessions;
use App\Support\UserAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

const CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36';
const SAFARI_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

function sessionFor(User $user, string $agent = CHROME_WINDOWS, string $ip = '203.0.113.7', ?int $lastActivity = null, ?int $signedInAt = null): string
{
    $id = Str::random(40);

    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => $ip,
        'user_agent' => $agent,
        'payload' => base64_encode(serialize(['_token' => 'x', 'auth_signed_in_at' => $signedInAt ?? now()->subHour()->timestamp])),
        'last_activity' => $lastActivity ?? now()->timestamp,
    ]);

    return $id;
}

function sessionsAdmin(): User
{
    $role = Role::findOrCreate('super admin', 'web');
    foreach (['admin.users.sessions', 'admin.users.view', 'admin.users.edit', 'profile.view', 'profile.update'] as $name) {
        $role->givePermissionTo(Permission::findOrCreate($name, 'web'));
    }

    return tap(User::factory()->create(['password' => bcrypt('secret-pass')]))->assignRole($role);
}

test('browsers, systems and device types are recognised', function (string $agent, array $expected) {
    expect(UserAgent::describe($agent))->toBe($expected);
})->with([
    'chrome windows' => [CHROME_WINDOWS, ['browser' => 'Chrome', 'os' => 'Windows', 'device' => 'desktop']],
    'safari iphone' => [SAFARI_IPHONE, ['browser' => 'Safari', 'os' => 'iPhone', 'device' => 'mobile']],
    'edge' => ['Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/129.0 Safari/537.36 Edg/129.0', ['browser' => 'Edge', 'os' => 'Windows', 'device' => 'desktop']],
    'firefox mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14.5; rv:130.0) Gecko/20100101 Firefox/130.0', ['browser' => 'Firefox', 'os' => 'macOS', 'device' => 'desktop']],
    'android tablet' => ['Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 Chrome/129.0 Safari/537.36', ['browser' => 'Chrome', 'os' => 'Android', 'device' => 'tablet']],
    'empty' => ['', ['browser' => 'Unknown browser', 'os' => 'Unknown system', 'device' => 'desktop']],
]);

describe('the sessions service', function () {
    test('lists only live sessions of the user, current first, keyed by a hash', function () {
        $user = User::factory()->create();
        $current = sessionFor($user, lastActivity: now()->subMinutes(30)->timestamp);
        $phone = sessionFor($user, SAFARI_IPHONE, '198.51.100.4');
        sessionFor($user, lastActivity: now()->subDays(2)->timestamp);
        sessionFor(User::factory()->create());

        $sessions = app(ActiveSessions::class)->forUser($user, $current);

        expect($sessions)->toHaveCount(2);
        expect($sessions[0])->is_current->toBeTrue()->key->toBe(hash('sha256', $current))->online->toBeFalse();
        expect($sessions[1])->browser->toBe('Safari')->os->toBe('iPhone')->ip->toBe('198.51.100.4')->online->toBeTrue();
        expect($sessions[1]['signed_in_at'])->not->toBeNull();
        expect($sessions->pluck('key')->all())->not->toContain($phone);
    });

    test('ending a session removes it and resets "remember me", but never ends the current one', function () {
        $user = User::factory()->create(['remember_token' => 'old-token']);
        $current = sessionFor($user);
        $other = sessionFor($user);
        $service = app(ActiveSessions::class);

        expect($service->end($user, hash('sha256', $current), $current))->toBeFalse();
        expect($service->end($user, str_repeat('a', 64)))->toBeFalse();
        expect($user->fresh()->remember_token)->toBe('old-token');

        expect($service->end($user, hash('sha256', $other), $current))->toBeTrue();
        expect(DB::table('sessions')->pluck('id')->all())->toBe([$current]);
        expect($user->fresh()->remember_token)->not->toBe('old-token');
    });
});

describe('profile', function () {
    test('your devices are listed without ever showing a session id', function () {
        $admin = sessionsAdmin();
        $phone = sessionFor($admin, SAFARI_IPHONE, '198.51.100.4');

        $this->actingAs($admin)->get(route('admin.profile.edit'))->assertOk()
            ->assertSee("Where you're signed in")->assertSee('Safari on iPhone')->assertSee('198.51.100.4')
            ->assertSee('Sign out other devices')
            ->assertDontSee($phone);
    });

    test('signing out a device needs your password', function () {
        $admin = sessionsAdmin();
        $phone = sessionFor($admin, SAFARI_IPHONE);
        $this->actingAs($admin);

        $this->delete(route('admin.profile.sessions.destroy', hash('sha256', $phone)), ['password' => 'wrong'])
            ->assertSessionHasErrorsIn('sessions', ['password' => 'That password is not correct.']);
        expect(DB::table('sessions')->where('id', $phone)->exists())->toBeTrue();

        $this->delete(route('admin.profile.sessions.destroy', hash('sha256', $phone)), ['password' => 'secret-pass'])
            ->assertSessionHas('success', 'That device has been signed out.');
        expect(DB::table('sessions')->where('id', $phone)->exists())->toBeFalse();
    });

    test('"sign out other devices" ends every other session of yours only', function () {
        $admin = sessionsAdmin();
        sessionFor($admin);
        sessionFor($admin, SAFARI_IPHONE);
        $someoneElse = sessionFor(User::factory()->create());

        $this->actingAs($admin)->delete(route('admin.profile.sessions.destroy-others'), ['password' => 'secret-pass'])
            ->assertSessionHas('success', 'Signed out of 2 other devices.');

        expect(DB::table('sessions')->pluck('id')->all())->toBe([$someoneElse]);
    });
});

describe('admins', function () {
    test('can see where a user is signed in and force them out everywhere', function () {
        $admin = sessionsAdmin();
        $editor = User::factory()->create(['name' => 'Asha', 'remember_token' => 'old-token']);
        sessionFor($editor);
        sessionFor($editor, SAFARI_IPHONE);
        $this->actingAs($admin);

        $this->get(route('admin.users.edit', $editor))->assertOk()->assertSee('Where Asha is signed in')->assertSee('Sign out everywhere')->assertSee('Safari on iPhone');

        $this->delete(route('admin.users.sessions.destroy-all', $editor))->assertSessionHas('success', 'Asha has been signed out of 2 devices.');

        expect(DB::table('sessions')->where('user_id', $editor->id)->count())->toBe(0);
        expect($editor->fresh()->remember_token)->not->toBe('old-token');
    });

    test('can sign a user out of one device', function () {
        $admin = sessionsAdmin();
        $editor = User::factory()->create();
        $laptop = sessionFor($editor);
        $phone = sessionFor($editor, SAFARI_IPHONE);

        $this->actingAs($admin)->delete(route('admin.users.sessions.destroy', [$editor, hash('sha256', $phone)]))->assertSessionHas('success');

        expect(DB::table('sessions')->pluck('id')->all())->toBe([$laptop]);
    });

    test('the user list marks who is active now', function () {
        $admin = sessionsAdmin();
        $editor = User::factory()->create(['name' => 'Online Olivia']);
        sessionFor($editor);

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertSee('title="Active now"', false);
    });

    test('only a super admin can sign a super admin out, and the permission is needed', function () {
        $boss = tap(User::factory()->create())->assignRole(Role::findOrCreate('super admin', 'web'));
        sessionFor($boss);

        $manager = tap(User::factory()->create())->assignRole(tap(Role::findOrCreate('manager', 'web'))->givePermissionTo(Permission::findOrCreate('admin.users.sessions', 'web')));
        $this->actingAs($manager)->delete(route('admin.users.sessions.destroy-all', $boss))->assertForbidden();

        $this->actingAs(User::factory()->create())->delete(route('admin.users.sessions.destroy-all', User::factory()->create()))->assertForbidden();

        expect(DB::table('sessions')->where('user_id', $boss->id)->count())->toBe(1);
    });
});
