<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Support\Maintenance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class MaintenanceController extends Controller
{
    public function update(Request $request)
    {
        Gate::authorize('admin.settings.maintenance.update');

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:1000'],
            'ends_at' => ['nullable', 'date'],
            'allowed_ips' => ['nullable', 'string', 'max:2000'],
        ]);

        $ips = collect(preg_split('/[\s,]+/', (string) ($validated['allowed_ips'] ?? '')))->filter()->unique()->values();
        $invalid = $ips->reject(function ($ip) {
            [$address, $bits] = array_pad(explode('/', $ip, 2), 2, null);

            return filter_var($address, FILTER_VALIDATE_IP) && ($bits === null || (ctype_digit($bits) && (int) $bits <= (str_contains($address, ':') ? 128 : 32)));
        });

        if ($invalid->isNotEmpty()) {
            return back()->withErrors(['allowed_ips' => 'Not a valid IP address: '.$invalid->implode(', ')])->withInput();
        }

        $wasOn = Maintenance::isOn();
        $enabled = (bool) $validated['enabled'];

        Maintenance::save([
            'enabled' => $enabled,
            'title' => $validated['title'],
            'message' => $validated['message'],
            'ends_at' => filled($validated['ends_at'] ?? null) ? Carbon::parse($validated['ends_at'])->format('Y-m-d H:i') : null,
            'allowed_ips' => $ips->all(),
        ]);

        if ($wasOn !== $enabled) {
            rescue(fn () => ActivityLogger::log($enabled ? 'enabled' : 'disabled', description: 'Maintenance mode '.($enabled ? 'switched on' : 'switched off')), report: false);
        }

        return back()->with('success', match (true) {
            $enabled && ! $wasOn => 'Maintenance mode is on. Visitors now see the maintenance page; you still see the site.',
            ! $enabled && $wasOn => 'Maintenance mode is off. The website is live again.',
            default => 'Maintenance settings saved.',
        });
    }

    public function preview()
    {
        Gate::authorize('admin.settings.maintenance.view');

        return response()->view('maintenance', ['maintenance' => Maintenance::settings(), 'back' => Maintenance::expectedBack(), 'preview' => true]);
    }
}
