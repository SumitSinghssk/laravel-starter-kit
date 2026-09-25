<?php

use App\Mail\TwoFactorNoticeMail;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Health\SystemHealth;
use App\Services\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    withoutBotTrap();
});

function tfaUser(array $permissions = ['dashboard.view', 'profile.view'], string $role = 'editor', bool $required = false): User
{
    $role = Role::findOrCreate($role, 'web');
    $role->forceFill(['requires_two_factor' => $required])->save();
    foreach ($permissions as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return tap(User::factory()->create(['password' => 'secret-pass-123', 'status' => 'active']))->assignRole($role);
}

function otp(string $secret, int $offset = 0): string
{
    $google = new Google2FA;

    return $google->oathTotp($secret, $google->getTimestamp() + $offset);
}

function enableTwoFactor(User $user): string
{
    $secret = (new Google2FA)->generateSecretKey(32);
    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => [hash_hmac('sha256', 'aaaaabbbbb', config('app.key')), hash_hmac('sha256', 'cccccddddd', config('app.key'))],
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $secret;
}

test('setting up shows a QR code, checks the code and hands out recovery codes once', function () {
    $user = tfaUser();

    $this->actingAs($user)->get(route('admin.two-factor.show'))->assertOk()->assertSee('Two-factor sign-in is off')->assertSee('Set up two-factor');

    $this->post(route('admin.two-factor.start'))->assertRedirect();
    $secret = Crypt::decryptString(session(TwoFactor::PENDING_SECRET));
    expect($secret)->toBeString()->and($user->fresh()->hasTwoFactor())->toBeFalse();

    $this->get(route('admin.two-factor.show'))->assertSee('<svg', false)->assertSee(TwoFactor::formatSecret($secret))->assertSee('Enter the 6-digit code it shows');

    $this->post(route('admin.two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrorsIn('twoFactor', 'code');
    expect($user->fresh()->hasTwoFactor())->toBeFalse();

    $this->post(route('admin.two-factor.confirm'), ['code' => otp($secret)])->assertSessionHas(TwoFactor::NEW_CODES);
    $codes = session(TwoFactor::NEW_CODES);

    $user->refresh();
    expect($user->hasTwoFactor())->toBeTrue()
        ->and($user->two_factor_secret)->toBe($secret)
        ->and(DB::table('users')->where('id', $user->id)->value('two_factor_secret'))->not->toContain($secret)
        ->and($codes)->toHaveCount(TwoFactor::RECOVERY_CODES)
        ->and($codes[0])->toMatch('/^[a-z0-9]{5}-[a-z0-9]{5}$/')
        ->and(ActivityLog::where('action', 'two_factor_enabled')->where('user_id', $user->id)->exists())->toBeTrue();
    Mail::assertSent(TwoFactorNoticeMail::class, fn ($mail) => $mail->event === 'enabled' && $mail->hasTo($user->email));

    $this->get(route('admin.two-factor.show'))->assertSee($codes[0])->assertSee('Save your recovery codes');
    $this->get(route('admin.two-factor.show'))->assertDontSee($codes[0])->assertSee('Two-factor sign-in is on');
});

test('signing in asks for the code after the password', function () {
    $user = tfaUser();
    $secret = enableTwoFactor($user);

    $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'secret-pass-123'])->assertRedirect(route('admin.two-factor.challenge'));
    $this->assertGuest();

    $this->get(route('admin.two-factor.challenge'))->assertOk()->assertSee('Two-factor sign-in')->assertSee($user->email);
    $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

    $this->post(route('admin.two-factor.verify'), ['code' => '123456'])->assertSessionHasErrors('code');
    $this->assertGuest();
    expect(ActivityLog::where('action', 'two_factor_failed')->where('user_id', $user->id)->exists())->toBeTrue();

    $this->post(route('admin.two-factor.verify'), ['code' => otp($secret)])->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user);
    expect(ActivityLog::where('action', 'login')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('a code cannot be used twice', function () {
    $user = tfaUser();
    $secret = enableTwoFactor($user);
    $code = otp($secret);

    $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'secret-pass-123']);
    $this->post(route('admin.two-factor.verify'), ['code' => $code])->assertRedirect(route('admin.dashboard'));
    $this->post(route('admin.logout'));

    $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'secret-pass-123']);
    $this->post(route('admin.two-factor.verify'), ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('a recovery code works once and sends a warning email', function () {
    $user = tfaUser();
    enableTwoFactor($user);

    $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'secret-pass-123']);
    $this->post(route('admin.two-factor.verify'), ['recovery' => 1, 'recovery_code' => 'AAAAA-bbbbb'])
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('warning');
    $this->assertAuthenticatedAs($user);

    expect(app(TwoFactor::class)->remainingCodes($user->fresh()))->toBe(1);
    Mail::assertSent(TwoFactorNoticeMail::class, fn ($mail) => $mail->event === 'recovery');

    $this->post(route('admin.logout'));
    $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'secret-pass-123']);
    $this->post(route('admin.two-factor.verify'), ['recovery' => 1, 'recovery_code' => 'aaaaa-bbbbb'])->assertSessionHasErrors('recovery_code');
    $this->assertGuest();
});

test('a trusted device skips the code until the password changes', function () {
    $user = tfaUser();
    $secret = enableTwoFactor($user);

    $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'secret-pass-123']);
    $response = $this->post(route('admin.two-factor.verify'), ['code' => otp($secret), 'trust' => 1]);
    $cookie = $response->getCookie(TwoFactor::TRUST_COOKIE);
    expect($cookie)->not->toBeNull();
    $this->post(route('admin.logout'));

    $this->withCookie(TwoFactor::TRUST_COOKIE, $cookie->getValue())
        ->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'secret-pass-123'])
        ->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($user);
    $this->post(route('admin.logout'));

    $user->forceFill(['password' => 'another-pass-456'])->save();
    $this->withCookie(TwoFactor::TRUST_COOKIE, $cookie->getValue())
        ->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'another-pass-456'])
        ->assertRedirect(route('admin.two-factor.challenge'));
    $this->assertGuest();
});

test('the code step times out and locks after too many wrong codes', function () {
    $user = tfaUser();
    $secret = enableTwoFactor($user);

    $this->get(route('admin.two-factor.challenge'))->assertRedirect(route('admin.login'));

    $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'secret-pass-123']);
    foreach (range(1, 5) as $attempt) {
        $this->post(route('admin.two-factor.verify'), ['code' => '111111']);
    }
    $this->post(route('admin.two-factor.verify'), ['code' => otp($secret)])->assertRedirect(route('admin.login'))->assertSessionHas('error');
    $this->assertGuest();

    $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'secret-pass-123']);
    $this->travel(TwoFactor::LOGIN_MINUTES + 1)->minutes();
    $this->post(route('admin.two-factor.verify'), ['code' => otp($secret)])->assertRedirect(route('admin.login'));
    $this->assertGuest();
});

test('a role can require two-factor sign-in, and its users are sent to set it up', function () {
    $admin = tfaUser(['admin.roles.view', 'admin.roles.update', 'dashboard.view'], 'super admin');
    $role = Role::findOrCreate('sales', 'web');
    $role->givePermissionTo(Permission::findOrCreate('dashboard.view', 'web'));
    $seller = tap(User::factory()->create(['status' => 'active']))->assignRole($role);

    $this->actingAs(tfaUser(['admin.roles.view'], 'viewer'))->post(route('admin.roles.two-factor', $role), ['required' => 1])->assertForbidden();

    $this->actingAs($admin)->post(route('admin.roles.two-factor', $role), ['required' => 1])->assertSessionHas('success');
    expect((bool) $role->fresh()->requires_two_factor)->toBeTrue();
    $this->get(route('admin.roles.index'))->assertSee('2FA required');

    $this->actingAs($seller->fresh())->get(route('admin.dashboard'))->assertRedirect(route('admin.two-factor.show'));
    $this->getJson(route('admin.notifications.list'))->assertForbidden();
    $this->get(route('admin.two-factor.show'))->assertOk()->assertSee('Your role requires two-factor sign-in');

    enableTwoFactor($seller);
    $this->actingAs($seller->fresh())->get(route('admin.dashboard'))->assertOk();
    $this->delete(route('admin.two-factor.destroy'), ['password' => 'password'])->assertSessionHas('error');
    expect($seller->fresh()->hasTwoFactor())->toBeTrue();
});

test('turning off and new recovery codes need the password', function () {
    $user = tfaUser();
    enableTwoFactor($user);

    $this->actingAs($user)->delete(route('admin.two-factor.destroy'), ['password' => 'wrong'])->assertSessionHasErrorsIn('twoFactor', 'password');
    expect($user->fresh()->hasTwoFactor())->toBeTrue();

    $this->post(route('admin.two-factor.recovery-codes'), ['password' => 'secret-pass-123'])->assertSessionHas(TwoFactor::NEW_CODES);
    expect(app(TwoFactor::class)->useRecoveryCode($user->fresh(), 'aaaaa-bbbbb'))->toBeFalse()
        ->and(app(TwoFactor::class)->useRecoveryCode($user->fresh(), session(TwoFactor::NEW_CODES)[0]))->toBeTrue();

    $this->post(route('admin.two-factor.start'), ['password' => ''])->assertSessionHasErrorsIn('twoFactor', 'password');

    $this->delete(route('admin.two-factor.destroy'), ['password' => 'secret-pass-123'])->assertSessionHas('success');
    expect($user->fresh()->hasTwoFactor())->toBeFalse()->and($user->fresh()->two_factor_secret)->toBeNull();
    Mail::assertSent(TwoFactorNoticeMail::class, fn ($mail) => $mail->event === 'disabled');
});

test('an admin can reset someone else\'s two-factor sign-in', function () {
    $admin = tfaUser(['admin.users.view', 'admin.users.edit', 'admin.users.two-factor'], 'super admin');
    $user = tfaUser();
    enableTwoFactor($user);

    $this->actingAs(tfaUser(['admin.users.view', 'admin.users.edit'], 'user manager'))->delete(route('admin.users.two-factor.reset', $user))->assertForbidden();

    $this->actingAs($admin)->get(route('admin.users.edit', $user))->assertSee('Reset two-factor');
    $this->delete(route('admin.users.two-factor.reset', $admin))->assertForbidden();
    $this->delete(route('admin.users.two-factor.reset', $user))->assertSessionHas('success');

    expect($user->fresh()->hasTwoFactor())->toBeFalse()
        ->and(ActivityLog::where('action', 'two_factor_reset')->where('model_id', $user->id)->value('description'))->toContain($admin->name);
    Mail::assertSent(TwoFactorNoticeMail::class, fn ($mail) => $mail->event === 'reset' && $mail->hasTo($user->email));

    $this->post(route('admin.logout'));
    $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'secret-pass-123'])->assertRedirect(route('admin.dashboard'));
});

test('the server command resets two-factor for a locked-out admin', function () {
    $user = tfaUser();
    enableTwoFactor($user);

    $this->artisan('admin:reset-two-factor', ['email' => $user->email])->expectsOutputToContain('was turned off')->assertExitCode(0);
    expect($user->fresh()->hasTwoFactor())->toBeFalse();

    $this->artisan('admin:reset-two-factor', ['email' => 'nobody@example.test'])->assertExitCode(1);
});

test('the account menu, profile, user list and system health show the status', function () {
    $admin = tfaUser(['dashboard.view', 'profile.view', 'admin.users.view', 'admin.users.edit'], 'super admin');
    enableTwoFactor($admin);
    tfaUser();

    $this->actingAs($admin->fresh())->get(route('admin.dashboard'))->assertSee(route('admin.two-factor.show'));
    $this->get(route('admin.profile.edit'))->assertSee('Two-factor sign-in')->assertSee('Manage');
    $this->get(route('admin.users.index'))->assertSee('Two-factor sign-in is on');

    $check = collect(SystemHealth::forRequest()->run()['groups']['security']['checks'])->firstWhere('key', 'security.two_factor');
    expect($check['value'])->toBe('1 of 2 users');
});
