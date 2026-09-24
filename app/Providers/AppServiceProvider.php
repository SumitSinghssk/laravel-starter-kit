<?php

namespace App\Providers;

use App\Services\Health\SystemHealth;
use App\Support\MailSettings;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        rescue(fn () => MailSettings::apply(), report: false);

        Queue::looping(fn () => SystemHealth::recordWorker());
    }
}
