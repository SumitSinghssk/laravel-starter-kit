<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Helpers\Settings;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ScriptSettingController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize('admin.settings.scripts.update');

        $data = $request->validate([
            'header_scripts' => 'nullable|string',
            'footer_scripts' => 'nullable|string',
            'header_css' => 'nullable|string',
            'footer_css' => 'nullable|string',
        ]);

        $setting = Setting::firstOrCreate(
            ['key' => 'script_settings'],
            ['value' => []]
        );

        $value = is_array($setting->value) ? $setting->value : [];

        $value['header_scripts'] = $data['header_scripts'] ?? null;
        $value['footer_scripts'] = $data['footer_scripts'] ?? null;
        $value['header_css'] = $data['header_css'] ?? null;
        $value['footer_css'] = $data['footer_css'] ?? null;

        $setting->value = $value;
        $setting->save();

        Settings::flush();

        return back()->with('scripts_success', 'Script settings updated successfully.');
    }
}
