<?php

use App\Models\Backup;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Backup\BackupRunner;
use App\Services\Backup\BackupSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    config(['backup.files_root' => Storage::disk('public')->path('')]);
    Cache::flush();
});

afterEach(fn () => Carbon::setTestNow());

function backupAdmin(): User
{
    $role = Role::findOrCreate('super admin', 'web');
    $role->givePermissionTo(Permission::where('name', 'like', 'admin.backups.%')->get());

    return tap(User::factory()->create())->assignRole($role);
}

function dumpOf(Backup $backup): string
{
    return implode('', gzfile(Storage::disk('local')->path($backup->path('database.sql.gz'))));
}

function zippedFiles(Backup $backup): array
{
    $names = [];

    foreach (collect($backup->parts)->pluck('file')->filter(fn ($f) => str_ends_with($f, '.zip')) as $part) {
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($backup->path($part)));
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();
    }

    sort($names);

    return $names;
}

describe('making backups', function () {
    test('a backup holds the database as SQL and the uploads as ZIP', function () {
        backupAdmin();
        Testimonial::create(['name' => "Priya O'Neil", 'quote' => 'Great work with our team.', 'status' => 'active']);
        Storage::disk('public')->put('blogs/cover.jpg', 'jpeg-bytes');
        Storage::disk('public')->put('uploads/2026/doc.txt', 'hello');

        $this->artisan('backup:run')->assertSuccessful();

        $backup = Backup::sole();
        expect($backup)->status->toBe('completed')->size->toBeGreaterThan(0);
        expect(collect($backup->parts)->pluck('file')->all())->toBe(['database.sql.gz', 'files-part-001.zip']);

        $sql = dumpOf($backup);
        expect($sql)->toContain('CREATE TABLE "testimonials"')->toContain("Priya O''Neil")->toContain('INSERT INTO "users"');

        expect(zippedFiles($backup))->toBe(['storage/app/public/blogs/cover.jpg', 'storage/app/public/uploads/2026/doc.txt']);
    });

    test('big tables are dumped in batches over many small steps, and nothing is lost', function () {
        config(['backup.rows_per_query' => 3]);
        foreach (range(1, 20) as $i) {
            Testimonial::create(['name' => "Client {$i}", 'quote' => 'A kind word number '.$i, 'status' => 'active']);
        }

        $runner = app(BackupRunner::class);
        $backup = $runner->start('manual', true, false);
        $steps = 0;

        while ($backup->refresh()->isRunning() && $steps < 500) {
            $runner->step($backup, 0);
            $steps++;
        }

        expect($backup->status)->toBe('completed')->and($steps)->toBeGreaterThan(20);
        expect(substr_count(dumpOf($backup), "'A kind word number "))->toBe(20);
    });

    test('an interrupted step is cut off on resume, so no rows are duplicated', function () {
        config(['backup.rows_per_query' => 2]);
        foreach (range(1, 9) as $i) {
            Testimonial::create(['name' => "Client {$i}", 'quote' => 'Words '.$i.' here', 'status' => 'active']);
        }

        $runner = app(BackupRunner::class);
        $backup = $runner->start('manual', true, false);

        while (($backup->refresh()->progress['current_table'] ?? null) !== 'testimonials' || ! ($backup->cursor['rows'] ?? [])) {
            $runner->step($backup, 0);
        }
        $file = Storage::disk('local')->path($backup->path('database.sql.gz'));
        $gz = gzopen($file, 'ab');
        gzwrite($gz, "INSERT INTO \"testimonials\" VALUES ('duplicate');\n");
        gzclose($gz);

        $runner->runToCompletion($backup);

        expect($backup->refresh()->status)->toBe('completed');
        expect(dumpOf($backup))->not->toContain('duplicate');
        expect(substr_count(dumpOf($backup), "'Words "))->toBe(9);
    });

    test('many uploads are split into several ZIP parts', function () {
        config(['backup.part_max_files' => 2]);
        foreach (range(1, 5) as $i) {
            Storage::disk('public')->put("media/photo-{$i}.jpg", "img {$i}");
        }

        $backup = app(BackupRunner::class)->runToCompletion(app(BackupRunner::class)->start('manual', false, true));

        expect(collect($backup->parts)->pluck('file')->all())->toBe(['files-part-001.zip', 'files-part-002.zip', 'files-part-003.zip']);
        expect(zippedFiles($backup))->toHaveCount(5);
    });

    test('only one backup runs at a time, but an abandoned one can be replaced', function () {
        $runner = app(BackupRunner::class);
        $first = $runner->start('manual', true, true);

        expect(fn () => $runner->start('manual', true, true))->toThrow(ValidationException::class);

        $first->forceFill(['last_step_at' => now()->subHour()])->save();
        $second = $runner->start('manual', true, true);

        expect($first->refresh()->status)->toBe('failed')->and($second->isRunning())->toBeTrue();
    });

    test('only the newest backups are kept', function () {
        $runner = app(BackupRunner::class);
        app(BackupSchedule::class)->save(['keep' => 2]);

        foreach (range(1, 3) as $i) {
            Carbon::setTestNow(Carbon::parse('2026-01-01 00:00')->addMinutes($i));
            $runner->runToCompletion($runner->start('manual', true, false));
        }

        expect(Backup::count())->toBe(2);
        expect(Storage::disk('local')->directories('backups'))->toHaveCount(2);
    });
});

describe('schedule', function () {
    test('daily, weekly and monthly slots and the next run', function () {
        $schedule = app(BackupSchedule::class);
        Carbon::setTestNow(Carbon::parse('2026-09-23 10:00'));

        $schedule->save(['enabled' => true, 'frequency' => 'daily', 'time' => '02:30']);
        expect($schedule->lastSlot()->toDateTimeString())->toBe('2026-09-23 02:30:00');

        $schedule->save(['frequency' => 'weekly', 'weekday' => 5, 'time' => '23:00']);
        expect($schedule->lastSlot()->toDateTimeString())->toBe('2026-09-18 23:00:00');
        expect($schedule->nextRun()->toDateTimeString())->toBe('2026-09-25 23:00:00');

        $schedule->save(['frequency' => 'monthly', 'monthday' => 28, 'time' => '01:00']);
        expect($schedule->lastSlot()->toDateTimeString())->toBe('2026-08-28 01:00:00');
        expect($schedule->nextRun()->toDateTimeString())->toBe('2026-09-28 01:00:00');
        expect($schedule->describe())->toBe('On day 28 of every month at 01:00');
    });

    test('the scheduled command backs up once per slot, and never before the schedule was set', function () {
        $schedule = app(BackupSchedule::class);
        Carbon::setTestNow(Carbon::parse('2026-09-23 01:00'));
        $schedule->save(['enabled' => true, 'frequency' => 'daily', 'time' => '02:00', 'include_files' => false]);

        $this->artisan('backup:run --scheduled')->assertSuccessful();
        expect(Backup::count())->toBe(0);
        expect(Cache::get('backup:scheduler-heartbeat'))->not->toBeNull();

        Carbon::setTestNow(Carbon::parse('2026-09-23 02:01'));
        $this->artisan('backup:run --scheduled')->assertSuccessful();
        $this->artisan('backup:run --scheduled')->assertSuccessful();

        expect(Backup::sole())->trigger->toBe('scheduled')->status->toBe('completed')->includes_files->toBeFalse()
            ->scheduled_for->toDateTimeString()->toBe('2026-09-23 02:00:00');
        expect($schedule->isDue())->toBeFalse();
    });

    test('nothing runs while automatic backups are off', function () {
        app(BackupSchedule::class)->save(['enabled' => false]);

        $this->artisan('backup:run --scheduled')->assertSuccessful();

        expect(Backup::count())->toBe(0);
    });
});

describe('admin page', function () {
    test('the page renders with the schedule form and history', function () {
        $this->actingAs(backupAdmin());
        app(BackupRunner::class)->runToCompletion(app(BackupRunner::class)->start('manual', true, false));

        $this->get(route('admin.backups.index'))->assertOk()
            ->assertSee('Automatic backups')->assertSee('adminTimePicker', false)->assertSee('Back up now')
            ->assertSee('Not set up on this server yet')->assertSee('schedule:run')
            ->assertSee('Completed');
    });

    test('the schedule is saved and validated', function () {
        $this->actingAs(backupAdmin());

        $this->post(route('admin.backups.settings'), ['enabled' => 1, 'frequency' => 'weekly', 'time' => '03:15', 'weekday' => 7, 'monthday' => 1, 'include_database' => 1, 'include_files' => 0, 'keep' => 10])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Every Sunday at 03:15'));

        $this->post(route('admin.backups.settings'), ['enabled' => 1, 'frequency' => 'hourly', 'time' => '25:00', 'include_database' => 0, 'include_files' => 0, 'keep' => 0])
            ->assertSessionHasErrors(['frequency', 'time', 'keep']);
    });

    test('"Back up now" is driven step by step from the page, then downloaded and deleted', function () {
        $this->actingAs(backupAdmin());
        Storage::disk('public')->put('a.png', 'png');

        $backup = $this->postJson(route('admin.backups.store'), ['include_database' => true, 'include_files' => true])->assertCreated()->json('backup');

        for ($i = 0; $i < 50 && $backup['status'] === 'running'; $i++) {
            $backup = $this->postJson($backup['step_url'])->assertOk()->json('backup');
        }

        expect($backup['status'])->toBe('completed')->and($backup['percent'])->toBe(100);

        $model = Backup::sole();
        $this->get(route('admin.backups.download', [$model, 'database.sql.gz']))->assertOk()->assertDownload("{$model->name}-database.sql.gz");
        $this->get(route('admin.backups.download', [$model, '..\\.env']))->assertNotFound();

        $this->delete(route('admin.backups.destroy', $model))->assertRedirect();
        expect(Backup::count())->toBe(0);
        Storage::disk('local')->assertMissing($model->directory());
    });

    test('people without the permission cannot see or make backups', function () {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.backups.index'))->assertForbidden();
        $this->postJson(route('admin.backups.store'), ['include_database' => true, 'include_files' => true])->assertForbidden();
    });
});
