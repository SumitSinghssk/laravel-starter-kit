<?php

namespace App\Providers;

use App\Support\MailSettings;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        rescue(fn () => MailSettings::apply(), report: false);
    }
}
