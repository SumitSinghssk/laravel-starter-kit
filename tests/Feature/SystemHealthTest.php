<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\Backup\BackupSchedule;
use App\Services\Health\SystemHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->sandbox = storage_path('framework/testing/health-'.Str::random(6));
    File::ensureDirectoryExists($this->sandbox);
});

afterEach(function () {
    foreach (glob($this->sandbox.'/*') ?: [] as $path) {
        if (is_link($path) || (PHP_OS_FAMILY === 'Windows' && realpath($path) !== $path)) {
            PHP_OS_FAMILY === 'Windows' ? @rmdir($path) : @unlink($path);
        }
    }
    File::deleteDirectory($this->sandbox);
});

function healthUser(bool $manage = true): User
{
    $role = Role::findOrCreate($manage ? 'super admin' : 'health viewer', 'web');
    $role->givePermissionTo(Permission::findOrCreate('admin.system-health.view', 'web'));
    if ($manage) {
        $role->givePermissionTo(Permission::findOrCreate('admin.system-health.manage', 'web'));
    }

    return tap(User::factory()->create())->assignRole($role);
}

function healthCheck(array $result, string $key): array
{
    foreach ($result['groups'] as $group) {
        foreach ($group['checks'] as $check) {
            if ($check['key'] === $key) {
                return $check;
            }
        }
    }

    throw new RuntimeException("No check {$key}");
}

function failedJob(?string $uuid = null): string
{
    $uuid ??= (string) Str::uuid();
    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['uuid' => $uuid, 'displayName' => 'App\\Jobs\\SendInvoice', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'data' => []]),
        'exception' => "RuntimeException: Invoice service unavailable\n#0 stack",
        'failed_at' => now()->subMinutes(3),
    ]);

    return $uuid;
}

test('the page needs permission', function () {
    $this->get(route('admin.system-health.index'))->assertRedirect();

    $this->actingAs(User::factory()->create())->get(route('admin.system-health.index'))->assertForbidden();

    $this->actingAs(healthUser(manage: false))->get(route('admin.system-health.index'))->assertOk()
        ->assertSee('System health')
        ->assertSee('Storage &amp; files', false)
        ->assertSee('Scheduler')
        ->assertSee('Copy report');
});

test('every group runs and the summary is stored for the sidebar badge', function () {
    $result = SystemHealth::forRequest()->run();

    expect(array_keys($result['groups']))->toBe(['app', 'php', 'database', 'storage', 'queue', 'scheduler', 'mail', 'cache', 'backups', 'security'])
        ->and(collect($result['groups'])->flatMap(fn ($g) => $g['checks'])->pluck('key'))->not->toContain('app.failed')
        ->and(SystemHealth::summary())->toMatchArray(['errors' => $result['summary']['errors'], 'warnings' => $result['summary']['warnings']])
        ->and($result['summary']['total'])->toBe($result['summary']['passed'] + $result['summary']['warnings'] + $result['summary']['errors'] + collect($result['groups'])->flatMap(fn ($g) => $g['checks'])->where('status', SystemHealth::INFO)->count());

    Cache::forever(SystemHealth::SUMMARY_KEY, [...$result['summary'], 'errors' => 3]);
    $this->actingAs(healthUser())->get(route('admin.dashboard'))->assertSee('3 problems found');
});

test('the storage link is recognised, missing, or blocked by a real folder', function () {
    $target = $this->sandbox.'/target';
    $link = $this->sandbox.'/storage';
    File::ensureDirectoryExists($target);
    $health = new SystemHealth;

    expect($health->storageLink($link, $target))->toMatchArray(['status' => SystemHealth::ERROR, 'value' => 'Missing']);

    File::ensureDirectoryExists($link);
    expect($health->storageLink($link, $target)['value'])->toContain('is a normal folder');
    File::deleteDirectory($link);

    File::link($target, $link);
    expect($health->storageLink($link, $target)['status'])->toBe(SystemHealth::OK);
});

test('create link makes the link, and refuses to replace a real folder', function () {
    $target = $this->sandbox.'/target';
    $link = $this->sandbox.'/storage';
    File::ensureDirectoryExists($target);
    config(['filesystems.links' => [$link => $target]]);

    $this->actingAs(healthUser(manage: false))->post(route('admin.system-health.storage-link'))->assertForbidden();

    File::ensureDirectoryExists($link);
    $this->actingAs(healthUser())->post(route('admin.system-health.storage-link'))->assertSessionHas('error');
    expect(realpath($link))->not->toBe(realpath($target));
    File::deleteDirectory($link);

    $this->post(route('admin.system-health.storage-link'))->assertSessionHas('success');
    expect(realpath($link))->toBe(realpath($target));
});

test('failed jobs are listed and can be retried or deleted', function () {
    config(['queue.default' => 'database']);
    $first = failedJob();
    $second = failedJob();

    $result = SystemHealth::forRequest()->run();
    expect(healthCheck($result, 'queue.failed'))->toMatchArray(['status' => SystemHealth::WARNING, 'value' => '2'])
        ->and($result['groups']['queue']['extra']['failed'][0])->toMatchArray(['name' => 'SendInvoice', 'error' => 'RuntimeException: Invoice service unavailable']);

    $this->actingAs(healthUser())->get(route('admin.system-health.index'))->assertSee('SendInvoice')->assertSee('Retry all');

    $this->post(route('admin.system-health.failed-jobs.retry', ['id' => $first]))->assertRedirect(route('admin.system-health.index').'#queue');
    expect(DB::table('failed_jobs')->where('uuid', $first)->exists())->toBeFalse()
        ->and(DB::table('jobs')->count())->toBe(1);

    $this->delete(route('admin.system-health.failed-jobs.delete', ['id' => $second]));
    expect(DB::table('failed_jobs')->count())->toBe(0);

    failedJob();
    failedJob();
    $this->delete(route('admin.system-health.failed-jobs.delete', ['id' => 'all']));
    expect(DB::table('failed_jobs')->count())->toBe(0);

    $this->actingAs(healthUser(manage: false))->post(route('admin.system-health.failed-jobs.retry', ['id' => 'all']))->assertForbidden();
    $this->actingAs(healthUser(manage: false))->get(route('admin.system-health.index'))->assertDontSee('Retry all');
});

test('jobs waiting with no worker are a problem, and a running worker is seen', function () {
    config(['queue.default' => 'database']);
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->subMinutes(10)->timestamp, 'created_at' => now()->subMinutes(10)->timestamp]);

    $result = SystemHealth::forRequest()->run();
    expect(healthCheck($result, 'queue.pending')['status'])->toBe(SystemHealth::ERROR)
        ->and(healthCheck($result, 'queue.worker'))->toMatchArray(['status' => SystemHealth::ERROR, 'value' => 'Not running']);

    SystemHealth::recordWorker();
    expect(healthCheck(SystemHealth::forRequest()->run(), 'queue.worker'))->toMatchArray(['status' => SystemHealth::OK, 'value' => 'Running']);
});

test('with nothing queued, a missing worker is only information', function () {
    config(['queue.default' => 'database']);
    Cache::forget(SystemHealth::WORKER_KEY);

    expect(healthCheck(SystemHealth::forRequest()->run(), 'queue.worker')['status'])->toBeIn([SystemHealth::INFO, SystemHealth::OK]);
});

test('the scheduler is a problem when it never ran and automatic backups are on', function () {
    Cache::forget(SystemHealth::SCHEDULER_KEY);
    Cache::forget('backup:scheduler-heartbeat');

    expect(healthCheck(SystemHealth::forRequest()->run(), 'scheduler.running'))->toMatchArray(['status' => SystemHealth::WARNING, 'value' => 'Not set up']);

    Setting::updateOrCreate(['key' => BackupSchedule::KEY], ['value' => [...app(BackupSchedule::class)->settings(), 'enabled' => true]]);
    expect(healthCheck(SystemHealth::forRequest()->run(), 'scheduler.running')['status'])->toBe(SystemHealth::ERROR);

    SystemHealth::recordScheduler();
    $result = SystemHealth::forRequest()->run();
    expect(healthCheck($result, 'scheduler.running'))->toMatchArray(['status' => SystemHealth::OK, 'value' => 'Running'])
        ->and(collect($result['groups']['scheduler']['extra']['tasks'])->pluck('name'))->toContain('backup:run --scheduled', 'trash:purge', 'health:check')->not->toContain('health:heartbeat');
});

test('email checks warn about the log mailer and the example address', function () {
    config(['mail.default' => 'log', 'mail.from.address' => 'hello@example.com']);
    $result = SystemHealth::forRequest()->run();

    expect(healthCheck($result, 'mail.mailer'))->toMatchArray(['status' => SystemHealth::WARNING, 'value' => 'log'])
        ->and(healthCheck($result, 'mail.from')['status'])->toBe(SystemHealth::WARNING);

    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.org', 'mail.mailers.smtp.port' => 587, 'mail.from.address' => 'shop@myshop.in']);
    $result = SystemHealth::forRequest()->run();

    expect(healthCheck($result, 'mail.mailer'))->toMatchArray(['status' => SystemHealth::OK, 'value' => 'SMTP · smtp.example.org:587'])
        ->and(healthCheck($result, 'mail.from')['status'])->toBe(SystemHealth::OK);
});

test('the mail connection check explains failures and needs SMTP', function () {
    config(['mail.default' => 'log']);
    $this->actingAs(healthUser(manage: false))->postJson(route('admin.system-health.mail-check'))
        ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'not sending through SMTP'));

    config(['mail.default' => 'smtp', 'mail.mailers.smtp' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'scheme' => 'smtp', 'auto_tls' => false, 'timeout' => 5]]);
    $this->postJson(route('admin.system-health.mail-check'))
        ->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'Could not connect to 127.0.0.1:1') || str_contains($m, 'No answer from'));
});

test('debug mode on the live site is a problem', function () {
    config(['app.debug' => true]);
    app()['env'] = 'production';

    expect(healthCheck(SystemHealth::forRequest()->run(), 'security.debug')['status'])->toBe(SystemHealth::ERROR);

    app()['env'] = 'testing';
    expect(healthCheck(SystemHealth::forRequest()->run(), 'security.debug')['status'])->toBe(SystemHealth::INFO);
});

test('php, database and cache checks pass in a healthy setup', function () {
    $result = SystemHealth::forRequest()->run();

    expect(healthCheck($result, 'database.connection')['status'])->toBe(SystemHealth::OK)
        ->and(healthCheck($result, 'database.migrations')['status'])->toBe(SystemHealth::OK)
        ->and(healthCheck($result, 'cache.store')['status'])->toBe(SystemHealth::OK)
        ->and(healthCheck($result, 'security.key')['status'])->toBe(SystemHealth::OK)
        ->and(healthCheck($result, 'app.php')['value'])->toBe(PHP_VERSION);
});

test('folder sizes load separately', function () {
    $this->actingAs(healthUser(manage: false))->getJson(route('admin.system-health.sizes'))
        ->assertOk()
        ->assertJsonStructure(['folders' => ['uploads' => ['label', 'bytes', 'size', 'complete'], 'backups', 'logs', 'cache']]);
});

test('the copyable report and the artisan command list every group', function () {
    $report = SystemHealth::report(SystemHealth::forRequest()->run());

    expect($report)->toContain('## Application', '## Queue', '## Security', 'Overall:');

    $this->artisan('health:check')->expectsOutputToContain('Scheduler')->expectsOutputToContain('checks passed');
});
