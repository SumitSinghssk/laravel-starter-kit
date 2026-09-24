<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;

class SettingController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize('admin.settings.view');

        $basic = Setting::where('key', 'basic_settings')->first();
        $script = Setting::where('key', 'script_settings')->first();

        $settings = is_array($basic?->value) ? $basic->value : [];
        $scriptSettings = is_array($script?->value) ? $script->value : [];

        $sitemapRecord = Setting::where('key', 'sitemap_info')->first();
        $sitemapInfo = $sitemapRecord?->value ?? [];
        $sitemapExists = file_exists(public_path('sitemap.xml'));

        $logFiles = (new LogController)->getLogFiles();

        $robotsPath = public_path('robots.txt');
        $robotsContent = File::exists($robotsPath) ? File::get($robotsPath) : '';
        $robotsExists = File::exists($robotsPath);

        return view('admin.settings.index', compact('settings', 'scriptSettings', 'sitemapInfo', 'sitemapExists', 'logFiles', 'robotsContent', 'robotsExists'));
    }
}
