<?php

namespace App\Providers;

use App\Services\Health\SystemHealth;
use App\Support\MailSettings;
use App\Support\SecuritySettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        rescue(fn () => MailSettings::apply(), report: false);

        Queue::looping(fn () => SystemHealth::recordWorker());

        $limit = fn (string $key) => max(1, (int) rescue(fn () => SecuritySettings::get($key), SecuritySettings::DEFAULTS[$key], false));

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute($limit('password_reset_per_minute'))->by('password-reset|'.$request->ip()));
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute($limit('two_factor_per_minute'))->by('two-factor|'.$request->ip()));
        RateLimiter::for('forms', fn (Request $request) => Limit::perMinute($limit('forms_per_minute'))->by('forms|'.$request->ip()));
        RateLimiter::for('email-preview', fn (Request $request) => Limit::perMinute(120)->by('email-preview|'.($request->user()?->id ?? $request->ip())));
        RateLimiter::for('email-test', fn (Request $request) => Limit::perMinute(6)->by('email-test|'.($request->user()?->id ?? $request->ip())));
    }
}
