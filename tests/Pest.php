<?php

use App\Helpers\Settings;
use App\Models\Setting;
use App\Support\SecuritySettings;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

function something() {}

function withoutBotTrap(): void
{
    Setting::updateOrCreate(['key' => SecuritySettings::KEY], ['value' => ['honeypot' => false]]);
    Settings::flush();
}
