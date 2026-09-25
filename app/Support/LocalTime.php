<?php

namespace App\Support;

use App\Helpers\Settings;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Throwable;

class LocalTime
{
    public const KEY = 'date_time_settings';

    public const DATE_FORMATS = ['d M Y', 'M j, Y', 'j F Y', 'd/m/Y', 'm/d/Y', 'd.m.Y', 'Y-m-d'];

    public const TIME_FORMATS = ['H:i' => '24-hour', 'g:i A' => '12-hour'];

    public const DEFAULT_DATE = 'd M Y';

    public const DEFAULT_TIME = 'H:i';

    public static function zone(): string
    {
        return self::settings()['zone'];
    }

    public static function dateFormat(): string
    {
        return self::settings()['date'];
    }

    public static function timeFormat(): string
    {
        return self::settings()['time'];
    }

    public static function forget(): void
    {
        app()->forgetInstance(self::KEY);
    }

    public static function toLocal(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value, config('app.timezone'));
        } catch (Throwable) {
            return null;
        }

        return $date->copy()->setTimezone(self::zone());
    }

    public static function date(mixed $value): ?string
    {
        return self::toLocal($value)?->format(self::dateFormat());
    }

    public static function time(mixed $value): ?string
    {
        return self::toLocal($value)?->format(self::timeFormat());
    }

    public static function dateTime(mixed $value, bool $withZone = false): ?string
    {
        $local = self::toLocal($value);

        if (! $local) {
            return null;
        }

        return $local->format(self::dateFormat()).', '.$local->format(self::timeFormat()).($withZone ? ' ('.str_replace('_', ' ', self::zone()).')' : '');
    }

    public static function day(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }

        return $date->format(self::dateFormat());
    }

    public static function forInput(mixed $value, bool $withTime = true): string
    {
        return self::toLocal($value)?->format($withTime ? 'Y-m-d\TH:i' : 'Y-m-d') ?? '';
    }

    public static function fromInput(?string $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value, self::zone())->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    public static function zoneLabel(?string $zone = null): string
    {
        $zone ??= self::zone();

        if ($zone === 'UTC') {
            return 'UTC';
        }

        try {
            $offset = Carbon::now($zone)->format('P');
        } catch (Throwable) {
            return $zone;
        }

        return str_replace('_', ' ', $zone).' (UTC'.($offset === '+00:00' ? '' : $offset).')';
    }

    public static function zoneOptions(): array
    {
        return collect(DateTimeZone::listIdentifiers())
            ->mapWithKeys(fn (string $zone) => [$zone => [
                'label' => self::zoneLabel($zone),
                'group' => str_contains($zone, '/') ? strstr($zone, '/', true) : 'Other',
            ]])
            ->all();
    }

    public static function dateFormatOptions(): array
    {
        $sample = Carbon::create(2026, 9, 24);

        return collect(self::DATE_FORMATS)->mapWithKeys(fn (string $format) => [$format => $sample->format($format)])->all();
    }

    public static function timeFormatOptions(): array
    {
        $sample = Carbon::create(2026, 9, 24, 14, 30);

        return collect(self::TIME_FORMATS)->mapWithKeys(fn (string $label, string $format) => [$format => $label.' ('.$sample->format($format).')'])->all();
    }

    private static function settings(): array
    {
        if (! app()->bound(self::KEY)) {
            app()->scoped(self::KEY, fn () => self::resolve());
        }

        return app(self::KEY);
    }

    public static function resolve(): array
    {
        $stored = Settings::get(self::KEY, []);
        $stored = is_array($stored) ? $stored : [];
        $zone = $stored['timezone'] ?? null;
        $date = $stored['date_format'] ?? null;
        $time = $stored['time_format'] ?? null;

        return [
            'zone' => $zone && in_array($zone, DateTimeZone::listIdentifiers(), true) ? $zone : config('app.timezone', 'UTC'),
            'date' => in_array($date, self::DATE_FORMATS, true) ? $date : self::DEFAULT_DATE,
            'time' => array_key_exists((string) $time, self::TIME_FORMATS) ? $time : self::DEFAULT_TIME,
        ];
    }
}
