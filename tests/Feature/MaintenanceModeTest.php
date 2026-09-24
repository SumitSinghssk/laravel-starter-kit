<?php

use App\Models\NotFoundLog;
use App\Models\User;
use App\Support\Maintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function maintenanceAdmin(): User
{
    $role = Role::findOrCreate('super admin', 'web');
    Permission::findOrCreate('admin.settings.view', 'web');
    $role->givePermissionTo(Permission::where('name', 'like', 'admin.settings%')->get());

    return tap(User::factory()->create())->assignRole($role);
}

describe('website', function () {
    test('the site works normally while maintenance mode is off', function () {
        $this->get('/')->assertOk();
    });

    test('visitors get the maintenance page with a 503, on every URL', function () {
        Maintenance::save(['enabled' => true, 'title' => 'Back in a bit', 'message' => "New shelves going up.\nSee you soon!"]);

        $this->get('/')->assertStatus(503)
            ->assertSee('Back in a bit')->assertSee('New shelves going up.<br />', false)
            ->assertHeader('Retry-After', '3600');
        $this->get('/some/unknown/page')->assertStatus(503);

        expect(NotFoundLog::count())->toBe(0);
    });

    test('the expected-back time is shown and sent as Retry-After, until it has passed', function () {
        $this->travelTo(now()->setTime(10, 0));
        Maintenance::save(['enabled' => true, 'ends_at' => now()->addHours(2)->format('Y-m-d H:i')]);

        $this->get('/')->assertSee('Expected back today at 12:00')->assertHeader('Retry-After', (string) (2 * 3600));

        $this->travel(3)->hours();
        $this->get('/')->assertStatus(503)->assertDontSee('Expected back');
    });

    test('signed-in admins see the site, with a reminder banner', function () {
        Maintenance::save(['enabled' => true]);

        $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertSee('Maintenance mode is on: visitors see the maintenance page.');
    });

    test('allowed IP addresses see the site', function () {
        Maintenance::save(['enabled' => true, 'allowed_ips' => ['10.0.0.0/8', '127.0.0.1']]);

        $this->get('/')->assertOk();
    });

    test('the admin panel and login stay reachable', function () {
        Maintenance::save(['enabled' => true]);

        $this->get(route('admin.login'))->assertOk();
    });
});

describe('settings', function () {
    test('an admin switches it on and off; the header shows it while on', function () {
        $this->actingAs(maintenanceAdmin());

        $this->post(route('admin.settings.maintenance.update'), [
            'enabled' => 1, 'title' => 'Upgrading', 'message' => 'Back soon.', 'ends_at' => '2030-01-01T09:30', 'allowed_ips' => "203.0.113.7\n198.51.100.0/24",
        ])->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'Maintenance mode is on'));

        expect(Maintenance::settings())
            ->enabled->toBeTrue()->title->toBe('Upgrading')->ends_at->toBe('2030-01-01 09:30')
            ->allowed_ips->toBe(['203.0.113.7', '198.51.100.0/24']);

        $this->get(route('admin.dashboard'))->assertSee('Maintenance on');
        $this->get(route('admin.settings.index', ['tab' => 'maintenance']))->assertOk()->assertSee('Maintenance mode is on')->assertSee('Add my IP');

        $this->post(route('admin.settings.maintenance.update'), ['enabled' => 0, 'title' => 'Upgrading', 'message' => 'Back soon.'])
            ->assertSessionHas('success', 'Maintenance mode is off. The website is live again.');

        expect(Maintenance::isOn())->toBeFalse();
        $this->get(route('admin.dashboard'))->assertDontSee('Maintenance on');
    });

    test('bad input is refused', function () {
        $this->actingAs(maintenanceAdmin());

        $this->post(route('admin.settings.maintenance.update'), ['enabled' => 1, 'title' => '', 'message' => '', 'allowed_ips' => ''])
            ->assertSessionHasErrors(['title', 'message']);
        $this->post(route('admin.settings.maintenance.update'), ['enabled' => 1, 'title' => 'x', 'message' => 'y', 'allowed_ips' => "1.2.3.4\nnot-an-ip\n10.0.0.0/99"])
            ->assertSessionHasErrors(['allowed_ips' => 'Not a valid IP address: not-an-ip, 10.0.0.0/99']);

        expect(Maintenance::isOn())->toBeFalse();
    });

    test('the preview shows the page to admins', function () {
        $this->actingAs(maintenanceAdmin());
        Maintenance::save(['title' => 'Preview heading']);

        $this->get(route('admin.settings.maintenance.preview'))->assertOk()->assertSee('Preview heading')->assertSee('this is what visitors see');
    });

    test('without permission it cannot be switched', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.settings.maintenance.update'), ['enabled' => 1, 'title' => 'x', 'message' => 'y'])
            ->assertForbidden();

        expect(Maintenance::isOn())->toBeFalse();
    });
});
