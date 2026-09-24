<?php

use App\Helpers\Settings;
use App\Mail\SmtpTestMail;
use App\Models\Setting;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => Settings::flush());

function mailAdmin(bool $canEdit = true): User
{
    $role = Role::findOrCreate($canEdit ? 'super admin' : 'mail viewer', 'web');
    $role->givePermissionTo(Permission::findOrCreate('admin.settings.view', 'web'), Permission::findOrCreate('admin.settings.email.view', 'web'));
    if ($canEdit) {
        $role->givePermissionTo(Permission::findOrCreate('admin.settings.email.update', 'web'));
    }

    return tap(User::factory()->create())->assignRole($role);
}

function smtpForm(array $overrides = []): array
{
    return [
        'enabled' => '1',
        'provider' => 'gmail',
        'host' => 'smtp.gmail.com',
        'port' => '587',
        'encryption' => 'tls',
        'username' => 'me@gmail.com',
        'password' => 'app-password-123',
        'from_address' => 'me@gmail.com',
        'from_name' => 'My Shop',
        'reply_to' => 'help@myshop.test',
        ...$overrides,
    ];
}

test('the email tab needs permission', function () {
    $this->actingAs(mailAdmin())->get(route('admin.settings.index', ['tab' => 'email']))->assertOk()
        ->assertSee('Email (SMTP)')->assertSee('Send a test email')->assertSee('Zoho Mail');

    $this->actingAs(mailAdmin(canEdit: false))->get(route('admin.settings.index', ['tab' => 'email']))->assertOk()
        ->assertSee('view-only access')->assertDontSee('Send a test email');

    $this->actingAs(mailAdmin(canEdit: false))->post(route('admin.settings.email.update'), smtpForm())->assertForbidden();
    $this->actingAs(mailAdmin(canEdit: false))->postJson(route('admin.settings.email.test'), smtpForm(['test_email' => 'a@b.test']))->assertForbidden();
});

test('settings are saved with the password encrypted and never shown', function () {
    $this->actingAs(mailAdmin())->post(route('admin.settings.email.update'), smtpForm())
        ->assertRedirect(route('admin.settings.index', ['tab' => 'email']))
        ->assertSessionHas('success');

    $stored = Setting::where('key', MailSettings::KEY)->first()->value;
    expect($stored['host'])->toBe('smtp.gmail.com')
        ->and($stored['password'])->not->toBe('app-password-123')
        ->and(Crypt::decryptString($stored['password']))->toBe('app-password-123')
        ->and(MailSettings::password())->toBe('app-password-123');

    $this->get(route('admin.settings.index', ['tab' => 'email']))->assertDontSee('app-password-123')->assertSee('saved · type to change');
});

test('an empty password keeps the saved one, and remove clears it', function () {
    $admin = mailAdmin();
    $this->actingAs($admin)->post(route('admin.settings.email.update'), smtpForm());

    $this->post(route('admin.settings.email.update'), smtpForm(['password' => '', 'from_name' => 'Renamed']));
    Settings::flush();
    expect(MailSettings::password())->toBe('app-password-123')->and(MailSettings::settings()['from_name'])->toBe('Renamed');

    $this->post(route('admin.settings.email.update'), smtpForm(['password' => '', 'remove_password' => '1']));
    Settings::flush();
    expect(MailSettings::password())->toBeNull();
});

test('turning it on needs a host, port and from address; bad values are refused', function (array $overrides, string $field) {
    $this->actingAs(mailAdmin())->post(route('admin.settings.email.update'), smtpForm($overrides))->assertSessionHasErrors($field);

    expect(Setting::where('key', MailSettings::KEY)->exists())->toBeFalse();
})->with([
    'no host' => [['host' => ''], 'host'],
    'url instead of host' => [['host' => 'https://smtp.gmail.com'], 'host'],
    'bad port' => [['port' => '99999'], 'port'],
    'no from address' => [['from_address' => ''], 'from_address'],
    'bad from address' => [['from_address' => 'not-an-email'], 'from_address'],
    'unknown encryption' => [['encryption' => 'starttls-please'], 'encryption'],
    'unknown provider' => [['provider' => 'pigeon'], 'provider'],
]);

test('switched off, the fields may stay empty', function () {
    $this->actingAs(mailAdmin())->post(route('admin.settings.email.update'), smtpForm(['enabled' => '0', 'host' => '', 'from_address' => '']))
        ->assertSessionHasNoErrors();
});

test('saved settings become the site mailer only when switched on', function () {
    config(['mail.default' => 'log']);
    $this->actingAs(mailAdmin())->post(route('admin.settings.email.update'), smtpForm(['encryption' => 'ssl', 'port' => '465']));
    Settings::flush();

    MailSettings::apply();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.gmail.com')
        ->and(config('mail.mailers.smtp.port'))->toBe(465)
        ->and(config('mail.mailers.smtp.scheme'))->toBe('smtps')
        ->and(config('mail.mailers.smtp.password'))->toBe('app-password-123')
        ->and(config('mail.from.address'))->toBe('me@gmail.com')
        ->and(config('mail.from.name'))->toBe('My Shop')
        ->and(config('mail.reply_to.address'))->toBe('help@myshop.test');

    $this->post(route('admin.settings.email.update'), smtpForm(['enabled' => '0']));
    Settings::flush();
    config(['mail.default' => 'log']);
    MailSettings::apply();
    expect(config('mail.default'))->toBe('log');
});

test('encryption choices map to the right connection', function () {
    expect(MailSettings::transport(['host' => 'h', 'port' => 587, 'encryption' => 'tls'], null))->toMatchArray(['scheme' => 'smtp', 'require_tls' => true, 'auto_tls' => true])
        ->and(MailSettings::transport(['host' => 'h', 'port' => 465, 'encryption' => 'ssl'], null))->toMatchArray(['scheme' => 'smtps', 'require_tls' => false])
        ->and(MailSettings::transport(['host' => 'h', 'port' => 25, 'encryption' => 'none'], 'pw'))->toMatchArray(['scheme' => 'smtp', 'auto_tls' => false, 'password' => 'pw']);
});

test('a successful test sends the test email with the unsaved values', function () {
    $mailer = Mail::mailer('array');
    Mail::shouldReceive('build')->once()->withArgs(fn ($config) => $config['host'] === 'smtp.zoho.com' && $config['password'] === 'typed-now')->andReturn($mailer);

    $this->actingAs(mailAdmin())->postJson(route('admin.settings.email.test'), smtpForm([
        'provider' => 'zoho', 'host' => 'smtp.zoho.com', 'password' => 'typed-now', 'test_email' => 'owner@myshop.test',
    ]))->assertOk()->assertJsonPath('ok', true)->assertJsonPath('message', fn ($m) => str_contains($m, 'owner@myshop.test'));

    $sent = $mailer->getSymfonyTransport()->messages();
    expect($sent)->toHaveCount(1)
        ->and($sent[0]->getOriginalMessage()->getSubject())->toStartWith('Test email from')
        ->and($sent[0]->getOriginalMessage()->getFrom()[0]->getAddress())->toBe('me@gmail.com');
    expect(Setting::where('key', MailSettings::KEY)->exists())->toBeFalse();
});

test('a test with an empty password uses the saved one', function () {
    $admin = mailAdmin();
    $this->actingAs($admin)->post(route('admin.settings.email.update'), smtpForm());
    Settings::flush();

    $mailer = Mail::mailer('array');
    Mail::shouldReceive('build')->once()->withArgs(fn ($config) => $config['password'] === 'app-password-123')->andReturn($mailer);

    $this->postJson(route('admin.settings.email.test'), smtpForm(['password' => '', 'test_email' => 'x@y.test']))->assertOk();
});

test('a failed test explains what went wrong', function () {
    $this->actingAs(mailAdmin())->postJson(route('admin.settings.email.test'), smtpForm([
        'host' => '127.0.0.1', 'port' => '1', 'encryption' => 'none', 'test_email' => 'x@y.test',
    ]))->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'Could not connect to 127.0.0.1:1') || str_contains($m, 'No answer from'))
        ->assertJsonStructure(['detail']);
});

test('error messages are turned into plain advice', function (string $error, string $expected) {
    expect(MailSettings::explain($error, ['host' => 'smtp.x.com', 'port' => 587]))->toContain($expected);
})->with([
    ['Failed to authenticate on SMTP server with username "a" using 2 possible authenticators. 535 5.7.8 Username and Password not accepted', 'App Password'],
    ['Connection could not be established with host "smtp.x.com:587": stream_socket_client(): php_network_getaddresses: getaddrinfo failed', 'could not be found'],
    ['Connection to "smtp.x.com:587" timed out.', 'No answer from smtp.x.com:587'],
    ['Expected response code "250" but got code "530", with message "530 5.7.0 Must issue a STARTTLS command first."', 'encrypted connection'],
    ['Expected response code "250" but got code "553", with message "553 Sender address rejected: not owned by user"', 'From address'],
]);

test('the test needs a valid address to send to, and is rate limited', function () {
    $admin = mailAdmin();

    $this->actingAs($admin)->postJson(route('admin.settings.email.test'), smtpForm(['test_email' => 'nope']))->assertStatus(422)->assertJsonValidationErrors('test_email');

    $mailer = Mail::mailer('array');
    Mail::shouldReceive('build')->andReturn($mailer);
    foreach (range(1, 5) as $ignored) {
        $this->postJson(route('admin.settings.email.test'), smtpForm(['test_email' => 'x@y.test']))->assertOk();
    }
    $this->postJson(route('admin.settings.email.test'), smtpForm(['test_email' => 'x@y.test']))->assertStatus(429);
});

test('the test email itself shows the connection details', function () {
    $html = (new SmtpTestMail(['host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls', 'from_address' => 'me@gmail.com']))->render();

    expect($html)->toContain('Your email settings work')->toContain('smtp.gmail.com:587')->toContain('TLS (STARTTLS)');
});
