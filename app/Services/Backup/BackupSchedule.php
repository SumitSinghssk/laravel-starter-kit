<?php

namespace App\Services\Backup;

use App\Models\Backup;
use App\Models\Setting;
use Illuminate\Support\Carbon;

class BackupSchedule
{
    public const KEY = 'backup_settings';

    public const WEEKDAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    public function settings(): array
    {
        $stored = Setting::where('key', self::KEY)->value('value');

        return [...config('backup.defaults'), ...(is_array($stored) ? $stored : [])];
    }

    public function save(array $settings): array
    {
        $previous = $this->settings();

        $value = [
            ...$previous,
            ...$settings,
            'active_since' => now()->toIso8601String(),
        ];

        Setting::updateOrCreate(['key' => self::KEY], ['value' => $value]);

        return $value;
    }

    public function lastSlot(?Carbon $now = null): ?Carbon
    {
        $s = $this->settings();

        if (! $s['enabled']) {
            return null;
        }

        $now ??= now();
        [$hour, $minute] = array_map('intval', explode(':', $s['time']));

        return match ($s['frequency']) {
            'weekly' => tap($now->copy()->startOfWeek(Carbon::MONDAY)->addDays($s['weekday'] - 1)->setTime($hour, $minute), function (Carbon $slot) use ($now) {
                if ($slot->gt($now)) {
                    $slot->subWeek();
                }
            }),
            'monthly' => tap($now->copy()->startOfMonth()->addDays($s['monthday'] - 1)->setTime($hour, $minute), function (Carbon $slot) use ($now, $s, $hour, $minute) {
                if ($slot->gt($now)) {
                    $slot->subMonthNoOverflow()->startOfMonth()->addDays($s['monthday'] - 1)->setTime($hour, $minute);
                }
            }),
            default => tap($now->copy()->setTime($hour, $minute), function (Carbon $slot) use ($now) {
                if ($slot->gt($now)) {
                    $slot->subDay();
                }
            }),
        };
    }

    public function nextRun(?Carbon $now = null): ?Carbon
    {
        $now ??= now();
        $last = $this->lastSlot($now);

        if (! $last) {
            return null;
        }

        if ($this->isDue($now)) {
            return $now->copy();
        }

        $s = $this->settings();

        return match ($s['frequency']) {
            'weekly' => $last->copy()->addWeek(),
            'monthly' => $last->copy()->startOfMonth()->addMonthNoOverflow()->addDays($s['monthday'] - 1)->setTimeFrom($last),
            default => $last->copy()->addDay(),
        };
    }

    public function isDue(?Carbon $now = null): bool
    {
        $slot = $this->lastSlot($now ??= now());

        if (! $slot) {
            return false;
        }

        $since = $this->settings()['active_since'] ?? null;
        if ($since && $slot->lt(Carbon::parse($since))) {
            return false;
        }

        return ! Backup::where('trigger', 'scheduled')->where('scheduled_for', '>=', $slot)->exists();
    }

    public function describe(): string
    {
        $s = $this->settings();

        if (! $s['enabled']) {
            return 'Automatic backups are off';
        }

        return match ($s['frequency']) {
            'weekly' => 'Every '.self::WEEKDAYS[$s['weekday']].' at '.$s['time'],
            'monthly' => 'On day '.$s['monthday'].' of every month at '.$s['time'],
            default => 'Every day at '.$s['time'],
        };
    }
}
