<?php

namespace App\Services;

use App\Mail\AccountLockedMail;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\SecuritySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class AccountLockout
{
    public function isLocked(User $user): bool
    {
        if ($user->locked_at === null) {
            return false;
        }

        if ($user->locked_until !== null && $user->locked_until->isPast()) {
            $user->forceFill(['locked_at' => null, 'locked_until' => null, 'failed_logins' => 0])->saveQuietly();

            return false;
        }

        return true;
    }

    public function message(User $user): string
    {
        if ($user->locked_until === null) {
            return 'This account is locked after too many failed sign-ins. Ask an administrator to unlock it.';
        }

        $minutes = max(1, (int) ceil(now()->diffInSeconds($user->locked_until) / 60));

        return "This account is locked after too many failed sign-ins. Try again in {$minutes} ".Str::plural('minute', $minutes).', or ask an administrator to unlock it.';
    }

    public function recordFailure(User $user, Request $request, string $what = 'password'): bool
    {
        $settings = SecuritySettings::all();

        if (! $settings['lockout_enabled'] || $this->isLocked($user)) {
            return $this->isLocked($user);
        }

        $failures = $user->failed_logins + 1;
        $user->forceFill(['failed_logins' => $failures])->saveQuietly();

        if ($failures < (int) $settings['lockout_attempts']) {
            return false;
        }

        $minutes = (int) $settings['lockout_minutes'];
        $user->forceFill([
            'locked_at' => now(),
            'locked_until' => $minutes > 0 ? now()->addMinutes($minutes) : null,
        ])->saveQuietly();

        $this->log($user, 'account_locked', "Locked after {$failures} failed sign-ins (last one: wrong {$what})".($minutes > 0 ? " for {$minutes} minutes" : ' until an administrator unlocks it'), $request, true);

        notify('Security', 'Account locked', "{$user->name}'s account was locked after {$failures} failed sign-ins", ['ip' => $request->ip(), 'user_agent' => $request->userAgent()], route('admin.users.edit', $user));

        try {
            Mail::to($user)->send(new AccountLockedMail($user, $failures, $request->ip(), $request->userAgent()));
        } catch (Throwable $e) {
            report($e);
        }

        return true;
    }

    public function recordSuccess(User $user): void
    {
        if ($user->failed_logins > 0 || $user->locked_at !== null) {
            $user->forceFill(['failed_logins' => 0, 'locked_at' => null, 'locked_until' => null])->saveQuietly();
        }
    }

    public function unlock(User $user, Request $request, ?User $by = null): void
    {
        $user->forceFill(['failed_logins' => 0, 'locked_at' => null, 'locked_until' => null])->saveQuietly();

        $this->log($user, 'account_unlocked', $by ? "Unlocked by {$by->name}" : 'Unlocked', $request, false, $by);
    }

    private function log(User $user, string $action, string $description, Request $request, bool $suspicious, ?User $by = null): void
    {
        rescue(fn () => ActivityLog::create([
            'user_id' => ($by ?? $user)->id,
            'action' => $action,
            'model_type' => User::class,
            'model_id' => $user->id,
            'model_name' => $user->name,
            'email' => $user->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'description' => $description,
            'is_suspicious' => $suspicious,
        ]), report: false);
    }
}
