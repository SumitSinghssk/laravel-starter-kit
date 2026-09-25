<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Helpers\Settings;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\LocalTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class BasicSettingController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize('admin.settings.basic-details.update');

        $data = $request->validate([
            'app_name' => 'required|string|max:255',
            'addresses' => 'nullable|array',
            'addresses.*.label' => 'nullable|string|max:100',
            'addresses.*.text' => 'nullable|string',
            'addresses.*.map_iframe' => ['nullable', 'string', 'max:2000',
                function ($attribute, $value, $fail) {
                    if (! empty($value) && ! str_starts_with(trim($value), '<iframe')) {
                        $fail('The map embed code must be a valid <iframe> tag from Google Maps.');
                    }
                },
            ],

            'phones' => 'nullable|array',
            'phones.*' => 'nullable|string',

            'emails' => 'nullable|array',
            'emails.*' => 'nullable|email',

            'social_links' => 'nullable|array',
            'social_links.*.platform' => 'required_with:social_links|string|max:50',
            'social_links.*.url' => 'required_with:social_links|url',

            'logo_light' => 'nullable|image|max:2048',
            'logo_dark' => 'nullable|image|max:2048',
            'favicon' => 'nullable|image|max:512',
        ]);

        $setting = Setting::firstOrCreate(
            ['key' => 'basic_settings'],
            ['value' => []]
        );

        $value = is_array($setting->value) ? $setting->value : [];

        $value['logo'] = $value['logo'] ?? ['light' => null, 'dark' => null];

        if ($request->hasFile('logo_light')) {
            if (! empty($value['logo']['light'])) {
                Storage::disk('public')->delete($value['logo']['light']);
            }
            $value['logo']['light'] = $request->file('logo_light')->store('settings/logos', 'public');
        }

        if ($request->hasFile('logo_dark')) {
            if (! empty($value['logo']['dark'])) {
                Storage::disk('public')->delete($value['logo']['dark']);
            }
            $value['logo']['dark'] = $request->file('logo_dark')->store('settings/logos', 'public');
        }

        if ($request->hasFile('favicon')) {
            if (! empty($value['favicon'])) {
                Storage::disk('public')->delete($value['favicon']);
            }
            $value['favicon'] = $request->file('favicon')->store('settings/favicons', 'public');
        }

        $value['app_name'] = $data['app_name'];
        $value['addresses'] = collect($data['addresses'] ?? [])
            ->filter(fn ($a) => ! empty($a['label']) || ! empty($a['text']))
            ->map(fn ($a) => [
                'label' => trim($a['label'] ?? ''),
                'text' => trim($a['text'] ?? ''),
                'map_iframe' => trim($a['map_iframe'] ?? ''),
            ])
            ->values()
            ->toArray();
        $value['phones'] = array_values(array_filter($data['phones'] ?? []));
        $value['emails'] = array_values(array_filter($data['emails'] ?? []));
        $value['social_links'] = collect($data['social_links'] ?? [])
            ->filter(fn ($s) => ! empty($s['platform']) && ! empty($s['url']))
            ->values()
            ->toArray();

        $setting->value = $value;
        $setting->save();

        Settings::flush();
        LocalTime::forget();

        return back()->with('success', 'Settings updated successfully.');
    }
}
