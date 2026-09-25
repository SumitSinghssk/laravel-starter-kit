<?php

use App\Enums\CommonStatusEnum;
use App\Mail\AdminPasswordResetMail;
use App\Mail\PasswordChangedMail;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

function resetAdmin(array $attributes = []): User
{
    return User::factory()->create(['email' => 'owner@myshop.test', 'password' => 'old-password-123', 'status' => CommonStatusEnum::ACTIVE->value, ...$attributes]);
}

function sentResetUrl(): string
{
    $url = null;
    Mail::assertSent(AdminPasswordResetMail::class, function (AdminPasswordResetMail $mail) use (&$url) {
        $url = $mail->content()->with['url'];

        return true;
    });

    return $url;
}

beforeEach(function () {
    config(['mail.default' => 'array']);
    withoutBotTrap();
});

test('the login page links to forgot password, and the pages load for guests', function () {
    $this->get(route('admin.login'))->assertOk()->assertSee(route('admin.password.request'))->assertSee('Forgot password?');

    $this->get(route('admin.password.request'))->assertOk()->assertSee('Forgot your password?')->assertSee('Send reset link')->assertDontSee('Admin workspace');

    $this->actingAs(resetAdmin())->get(route('admin.password.request'))->assertRedirect();
});

test('a reset link is emailed to an active admin', function () {
    Mail::fake();
    $user = resetAdmin();

    $this->post(route('admin.password.email'), ['email' => 'owner@myshop.test'], ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0'])
        ->assertRedirect()
        ->assertSessionHas('reset_link_sent', 'owner@myshop.test');

    Mail::assertSent(AdminPasswordResetMail::class, fn ($mail) => $mail->hasTo('owner@myshop.test'));
    $html = Mail::sent(AdminPasswordResetMail::class)->first()->render();
    expect($html)->toContain('Choose a new password')->toContain('Chrome on Windows')->toContain('expires in 60 minutes')
        ->and(sentResetUrl())->toStartWith(url('admin/reset-password/'))->toContain('email=owner%40myshop.test')
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeTrue()
        ->and(ActivityLog::where('action', 'password_reset_requested')->where('user_id', $user->id)->exists())->toBeTrue();

    $this->get(route('admin.password.request'))->assertSee('Check your email')->assertSee('owner@myshop.test');
});

test('the answer is the same whether or not the account exists, and inactive or deleted accounts get nothing', function () {
    Mail::fake();
    resetAdmin(['email' => 'inactive@myshop.test', 'status' => CommonStatusEnum::INACTIVE->value]);
    resetAdmin(['email' => 'deleted@myshop.test'])->delete();

    foreach (['nobody@myshop.test', 'inactive@myshop.test', 'deleted@myshop.test'] as $email) {
        $this->post(route('admin.password.email'), ['email' => $email])->assertSessionHas('reset_link_sent', $email)->assertSessionHasNoErrors();
    }

    Mail::assertNothingSent();
});

test('asking again straight away does not send a second email', function () {
    Mail::fake();
    resetAdmin();

    $this->post(route('admin.password.email'), ['email' => 'owner@myshop.test']);
    $this->post(route('admin.password.email'), ['email' => 'owner@myshop.test'])->assertSessionHas('reset_link_sent');

    Mail::assertSentCount(1);
});

test('the forgot form is rate limited per visitor', function () {
    Mail::fake();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('admin.password.email'), ['email' => "someone{$attempt}@myshop.test"])->assertRedirect();
    }

    $this->post(route('admin.password.email'), ['email' => 'someone6@myshop.test'])->assertStatus(429);
});

test('the link sets a new password, signs out every device and confirms by email', function () {
    Mail::fake();
    $user = resetAdmin();
    DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'ip_address' => '1.2.3.4', 'user_agent' => 'x', 'payload' => '', 'last_activity' => now()->timestamp]);
    $oldRemember = $user->remember_token;

    $this->post(route('admin.password.email'), ['email' => $user->email]);
    $url = sentResetUrl();

    $this->get($url)->assertOk()->assertSee('Choose a new password')->assertSee('owner@myshop.test');

    $token = basename(parse_url($url, PHP_URL_PATH));
    $this->post(route('admin.password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'brand-new-pass-456', 'password_confirmation' => 'brand-new-pass-456'])
        ->assertRedirect(route('admin.login'))
        ->assertSessionHas('success');

    $user->refresh();
    expect(Hash::check('brand-new-pass-456', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe($oldRemember)
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse()
        ->and(ActivityLog::where('action', 'password_reset')->where('user_id', $user->id)->value('description'))->toContain('signed out of 1 session');
    Mail::assertSent(PasswordChangedMail::class, fn ($mail) => $mail->hasTo($user->email));

    $this->get(route('admin.login'))->assertSee('Your password was changed');
    $this->post(route('admin.login.store'), ['email' => $user->email, 'password' => 'brand-new-pass-456'])->assertRedirect(route('admin.dashboard'));
});

test('a used, wrong or expired link is refused', function () {
    Mail::fake();
    $user = resetAdmin();
    $token = Password::broker('users')->createToken($user);

    $this->get(route('admin.password.reset', ['token' => 'wrong', 'email' => $user->email]))->assertOk()->assertSee("This link doesn't work")->assertSee('Get a new link');
    $this->get(route('admin.password.reset', ['token' => $token]))->assertSee("This link doesn't work");

    $this->post(route('admin.password.update'), ['token' => 'wrong', 'email' => $user->email, 'password' => 'brand-new-pass-456', 'password_confirmation' => 'brand-new-pass-456'])
        ->assertSessionHasErrors('password');
    expect(Hash::check('old-password-123', $user->fresh()->password))->toBeTrue();

    $this->post(route('admin.password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'brand-new-pass-456', 'password_confirmation' => 'brand-new-pass-456']);
    $this->get(route('admin.password.reset', ['token' => $token, 'email' => $user->email]))->assertSee("This link doesn't work");

    $expired = Password::broker('users')->createToken($user);
    $this->travel(61)->minutes();
    $this->get(route('admin.password.reset', ['token' => $expired, 'email' => $user->email]))->assertSee("This link doesn't work");
});

test('the new password must be confirmed and strong enough', function () {
    $user = resetAdmin();
    $token = Password::broker('users')->createToken($user);

    $this->post(route('admin.password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'brand-new-pass-456', 'password_confirmation' => 'different'])
        ->assertSessionHasErrors('password');
    $this->post(route('admin.password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors('password');

    expect(Hash::check('old-password-123', $user->fresh()->password))->toBeTrue();
});

test('the forgot page warns when email cannot be delivered', function () {
    config(['mail.default' => 'log']);
    $this->get(route('admin.password.request'))->assertSee("Email isn't set up on this site yet", false);

    config(['mail.default' => 'smtp']);
    $this->get(route('admin.password.request'))->assertDontSee("Email isn't set up on this site yet", false);
});

test('a mail failure is logged and the link is not left behind', function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 3]]);
    $user = resetAdmin();

    $this->post(route('admin.password.email'), ['email' => $user->email])->assertSessionHas('reset_link_sent');

    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();
});

test('the server command sets a password when email is not available', function () {
    Mail::fake();
    $user = resetAdmin();

    $this->artisan('admin:reset-password', ['email' => $user->email])
        ->expectsQuestion('New password (at least 8 characters)', 'typed-on-server-789')
        ->expectsQuestion('Type it again', 'typed-on-server-789')
        ->expectsOutputToContain('Password changed for')
        ->assertExitCode(0);
    expect(Hash::check('typed-on-server-789', $user->fresh()->password))->toBeTrue();

    $this->artisan('admin:reset-password', ['email' => $user->email, '--generate' => true])->expectsOutputToContain('New password:')->assertExitCode(0);
    expect(Hash::check('typed-on-server-789', $user->fresh()->password))->toBeFalse();

    $this->artisan('admin:reset-password', ['email' => 'nobody@myshop.test'])->expectsOutputToContain('No admin account')->assertExitCode(1);
    $this->artisan('admin:reset-password', ['email' => $user->email])
        ->expectsQuestion('New password (at least 8 characters)', 'one-password-111')
        ->expectsQuestion('Type it again', 'other-password-222')
        ->expectsOutputToContain('did not match')
        ->assertExitCode(1);
});
