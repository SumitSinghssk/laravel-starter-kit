<?php

use App\Enums\CommonStatusEnum;
use App\Http\Controllers\Admin\NotFoundController;
use App\Models\Backup;
use App\Models\GalleryUpload;
use App\Models\User;
use App\Services\AdminPasswordReset;
use App\Services\Backup\BackupRunner;
use App\Services\Backup\BackupSchedule;
use App\Services\Health\SystemHealth;
use App\Services\Trash\TrashManager;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('gallery:prune-uploads', function () {
    $this->info(GalleryUpload::pruneStale().' unfinished upload(s) removed.');
})->purpose('Delete abandoned chunked gallery uploads');

Schedule::command('gallery:prune-uploads')->hourly();

Artisan::command('trash:purge', function () {
    $this->info(app(TrashManager::class)->purge().' item(s) permanently deleted from the trash.');
})->purpose('Permanently delete old items from the admin trash');

Schedule::command('trash:purge')->daily();

Artisan::command('backup:run {--scheduled : Only run when the admin schedule says a backup is due} {--database-only} {--files-only}', function () {
    $runner = app(BackupRunner::class);
    $schedule = app(BackupSchedule::class);

    if ($this->option('scheduled')) {
        Cache::forever('backup:scheduler-heartbeat', now()->timestamp);

        $backup = Backup::where('status', Backup::RUNNING)->latest('id')->first();

        if ($backup && ! $backup->isStalled()) {
            return;
        }

        if (! $backup) {
            if (! $schedule->isDue()) {
                return;
            }

            $settings = $schedule->settings();
            $backup = $runner->start('scheduled', $settings['include_database'], $settings['include_files'], null, $schedule->lastSlot());
        }
    } else {
        $backup = $runner->start('manual', ! $this->option('files-only'), ! $this->option('database-only'));
    }

    $this->info("Backing up to {$backup->name}…");
    $backup = $runner->runToCompletion($backup, fn ($b) => $this->line("  {$b->percent()}%  {$b->statusLine()}"));

    $backup->status === Backup::COMPLETED
        ? $this->info('Done: '.Number::fileSize($backup->size, 1).'.')
        : $this->error('Backup failed: '.$backup->error);
})->purpose('Make a backup (database + uploaded files), or the scheduled one when it is due');

Schedule::command('backup:run --scheduled')->everyMinute()->withoutOverlapping(720);

Schedule::call(fn () => NotFoundController::prune())->daily()->name('not-found:prune');

Artisan::command('health:check', function () {
    $result = SystemHealth::forRequest()->run();

    foreach ($result['groups'] as $group) {
        $this->line('');
        $this->line("<options=bold>{$group['label']}</>");

        foreach ($group['checks'] as $check) {
            $tag = match ($check['status']) {
                SystemHealth::ERROR => '<fg=red>✗</>',
                SystemHealth::WARNING => '<fg=yellow>!</>',
                SystemHealth::OK => '<fg=green>✓</>',
                default => '<fg=gray>·</>',
            };
            $this->line("  {$tag} {$check['label']}: ".trim(($check['value'] ? $check['value'].'. ' : '').$check['message']));
        }
    }

    $summary = $result['summary'];
    $this->line('');
    $this->line("{$summary['errors']} problem(s), {$summary['warnings']} warning(s), {$summary['passed']} of {$summary['total']} checks passed.");

    return $summary['errors'] > 0 ? 1 : 0;
})->purpose('Check the server, queue, scheduler, email and storage, like the admin System health page');

Artisan::command('admin:reset-password {email : The admin account\'s email address} {--generate : Create a random password instead of asking for one}', function (AdminPasswordReset $resets) {
    $user = User::where('email', $this->argument('email'))->first();

    if (! $user) {
        $this->error('No admin account uses that email address.');

        return 1;
    }

    if ($this->option('generate') || ! $this->input->isInteractive()) {
        $password = Str::password(16, symbols: false);
    } else {
        $password = $this->secret('New password (at least 8 characters)');

        if ($password !== $this->secret('Type it again')) {
            $this->error('The passwords did not match. Nothing was changed.');

            return 1;
        }
    }

    $validator = Validator::make(['password' => $password], ['password' => ['required', 'string', Password::defaults()]]);
    if ($validator->fails()) {
        $this->error($validator->errors()->first('password'));

        return 1;
    }

    $ended = $resets->changePassword($user, $password, null, null, 'server command');

    $this->info("Password changed for {$user->name} ({$user->email}). Signed out of {$ended} ".Str::plural('session', $ended).'.');
    if ($this->option('generate') || ! $this->input->isInteractive()) {
        $this->line("New password: <options=bold>{$password}</>");
        $this->comment('Share it privately and ask them to change it after signing in.');
    }
    if ($user->status?->value !== CommonStatusEnum::ACTIVE->value || $user->trashed()) {
        $this->warn('Note: this account is inactive or in the trash, so it still cannot sign in.');
    }

    return 0;
})->purpose('Set a new password for an admin account, for when the reset email cannot be used');

Schedule::call(fn () => SystemHealth::recordScheduler())->everyMinute()->name('health:heartbeat');
Schedule::command('health:check')->hourly();
