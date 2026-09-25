<?php

use App\Helpers\Settings;
use App\Models\Enquiry;
use App\Models\Setting;
use App\Models\User;
use App\Services\Backup\BackupSchedule;
use App\Support\LocalTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Settings::flush();
    LocalTime::forget();
});

afterEach(fn () => Carbon::setTestNow());

function dtUser(array $permissions = ['admin.settings.view', 'admin.settings.date-time.view', 'admin.settings.date-time.update'], string $role = 'super admin'): User
{
    $role = Role::findOrCreate($role, 'web');
    foreach ($permissions as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return tap(User::factory()->create(['status' => 'active']))->assignRole($role);
}

function siteDateTime(string $zone, string $date = 'd M Y', string $time = 'H:i'): void
{
    Setting::updateOrCreate(['key' => LocalTime::KEY], ['value' => ['timezone' => $zone, 'date_format' => $date, 'time_format' => $time]]);
    Settings::flush();
    LocalTime::forget();
}

test('an admin sets one timezone and date format for the whole site in its own settings tab', function () {
    $admin = dtUser();
    $tab = route('admin.settings.index', ['tab' => 'date-time']);

    $this->actingAs($admin)->get($tab)->assertOk()->assertSee('Date &amp; time', false)->assertSee('Save date & time', false)
        ->assertDontSee('Your preferences')->assertDontSee('Site defaults');

    $this->put(route('admin.settings.date-time.update'), ['timezone' => 'Asia/Kolkata', 'date_format' => 'd/m/Y', 'time_format' => 'g:i A'])
        ->assertRedirect($tab)
        ->assertSessionHas('success');

    expect(LocalTime::zone())->toBe('Asia/Kolkata')
        ->and(LocalTime::dateFormat())->toBe('d/m/Y')
        ->and(LocalTime::timeFormat())->toBe('g:i A')
        ->and(LocalTime::dateTime(Carbon::parse('2026-09-24 20:15:00', 'UTC')))->toBe('25/09/2026, 1:45 AM');
});

test('only people with permission can change it; others see it read-only or not at all', function () {
    $viewer = dtUser(['admin.settings.view', 'admin.settings.date-time.view'], 'viewer');

    $this->actingAs($viewer)->get(route('admin.settings.index', ['tab' => 'date-time']))->assertOk()->assertDontSee('Save date & time', false);
    $this->put(route('admin.settings.date-time.update'), ['timezone' => 'Asia/Kolkata', 'date_format' => 'd M Y', 'time_format' => 'H:i'])->assertForbidden();

    $other = dtUser(['admin.settings.view'], 'other');
    $this->actingAs($other)->get(route('admin.settings.index'))->assertDontSee('Save date & time', false);

    expect(LocalTime::zone())->toBe('UTC');
});

test('bad values are refused', function () {
    $this->actingAs(dtUser())->put(route('admin.settings.date-time.update'), ['timezone' => 'Mars/Olympus', 'date_format' => 'Y', 'time_format' => 'H'])
        ->assertSessionHasErrors(['timezone', 'date_format', 'time_format']);
});

test('there are no personal preferences or separate page', function () {
    $this->actingAs(dtUser())->get('/admin/account/preferences')->assertNotFound();

    expect(Schema::hasColumn('users', 'timezone'))->toBeFalse();
});

test('it defaults to the server timezone until an admin sets one', function () {
    expect(LocalTime::zone())->toBe(config('app.timezone'))
        ->and(LocalTime::dateTime(Carbon::parse('2026-09-24 20:15:00', 'UTC')))->toBe('24 Sep 2026, 20:15');
});

test('calendar days are not shifted by the timezone', function () {
    siteDateTime('America/Los_Angeles');

    expect(LocalTime::day('2026-09-24'))->toBe('24 Sep 2026')
        ->and(LocalTime::date(Carbon::parse('2026-09-24 00:00', 'UTC')))->toBe('23 Sep 2026');
});

test('times typed into forms are read in the site timezone', function () {
    siteDateTime('Asia/Kolkata');

    expect(LocalTime::fromInput('2026-09-24T10:00')->toDateTimeString())->toBe('2026-09-24 04:30:00')
        ->and(LocalTime::forInput(Carbon::parse('2026-09-24 04:30:00', 'UTC')))->toBe('2026-09-24T10:00');
});

test('the admin shows dates in the site format and timezone', function () {
    siteDateTime('Asia/Kolkata', 'd.m.Y');
    $user = dtUser(['admin.enquiries.view'], 'enquiries');
    $enquiry = Enquiry::create(['source' => 'contact-form', 'data' => ['name' => 'Ada', 'email' => 'a@b.test', 'message' => 'Hi'], 'status' => 'new']);
    $enquiry->forceFill(['created_at' => Carbon::parse('2026-09-24 20:15:00', 'UTC')])->saveQuietly();

    $this->actingAs($user)->get(route('admin.enquiries.index'))->assertSee('25.09.2026');
    $this->get(route('admin.enquiries.show', $enquiry))->assertSee('25.09.2026, 01:45');
});

test('scheduled backups run at the chosen time in the site timezone', function () {
    siteDateTime('Asia/Kolkata');
    Carbon::setTestNow(Carbon::parse('2026-09-23 10:00', 'UTC'));
    $schedule = app(BackupSchedule::class);
    $schedule->save(['enabled' => true, 'frequency' => 'daily', 'time' => '02:00']);

    expect($schedule->lastSlot()->toDateTimeString())->toBe('2026-09-22 20:30:00')
        ->and($schedule->nextRun()->toDateTimeString())->toBe('2026-09-23 20:30:00')
        ->and($schedule->describe())->toBe('Every day at 02:00 (Asia/Kolkata time)');
});
