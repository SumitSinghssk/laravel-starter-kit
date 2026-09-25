<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Helpers\Settings;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\LocalTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DateTimeSettingController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize('admin.settings.date-time.update');

        $validated = $request->validate([
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'date_format' => ['required', Rule::in(LocalTime::DATE_FORMATS)],
            'time_format' => ['required', Rule::in(array_keys(LocalTime::TIME_FORMATS))],
        ], [
            'timezone.in' => 'Pick a timezone from the list.',
        ]);

        Setting::updateOrCreate(['key' => LocalTime::KEY], ['value' => $validated]);

        Settings::flush();
        LocalTime::forget();

        return to_route('admin.settings.index', ['tab' => 'date-time'])
            ->with('success', 'Date and time settings saved. The whole admin and scheduled backups now use '.str_replace('_', ' ', $validated['timezone']).' time.');
    }
}
