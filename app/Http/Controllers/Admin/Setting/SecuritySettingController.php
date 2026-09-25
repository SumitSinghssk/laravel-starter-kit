<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use App\Models\BlockedRequest;
use App\Models\IpBlock;
use App\Services\Firewall;
use App\Support\SecuritySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SecuritySettingController extends Controller
{
    public function __construct(private Firewall $firewall) {}

    public function update(Request $request)
    {
        Gate::authorize('admin.settings.security.update');

        $validated = $request->validate([
            'lockout_enabled' => ['required', 'boolean'],
            'lockout_attempts' => ['required', 'integer', 'between:3,50'],
            'lockout_minutes' => ['required', 'integer', 'between:0,10080'],
            'idle_minutes' => ['required', 'integer', 'between:0,1440'],
            'login_per_minute' => ['required', 'integer', 'between:1,120'],
            'password_reset_per_minute' => ['required', 'integer', 'between:1,120'],
            'two_factor_per_minute' => ['required', 'integer', 'between:1,120'],
            'forms_per_minute' => ['required', 'integer', 'between:1,120'],
            'honeypot' => ['required', 'boolean'],
            'min_submit_seconds' => ['required', 'integer', 'between:0,30'],
            'captcha' => ['required', Rule::in(array_keys(SecuritySettings::CAPTCHAS))],
            'captcha_site_key' => ['nullable', 'string', 'max:200', Rule::requiredIf(fn () => $request->input('captcha') !== 'none')],
            'captcha_secret' => ['nullable', 'string', 'max:200'],
            'captcha_on_login' => ['required', 'boolean'],
            'auto_block' => ['required', 'boolean'],
            'auto_block_after' => ['required', 'integer', 'between:5,1000'],
            'auto_block_window' => ['required', 'integer', 'between:1,1440'],
            'auto_block_minutes' => ['required', 'integer', 'between:1,43200'],
            'log_days' => ['required', 'integer', 'between:1,365'],
        ], [
            'captcha_site_key.required' => 'Add the site key from your CAPTCHA provider, or turn the CAPTCHA off.',
            'lockout_attempts.between' => 'Use between 3 and 50 attempts.',
        ]);

        $removeSecret = $request->boolean('remove_captcha_secret');
        $newSecret = $request->input('captcha_secret');

        if ($validated['captcha'] !== 'none' && blank($newSecret) && ($removeSecret || blank(SecuritySettings::captchaSecret()))) {
            return back()->withInput()->withErrors(['captcha_secret' => 'Add the secret key from your CAPTCHA provider, or turn the CAPTCHA off.']);
        }

        unset($validated['captcha_secret']);
        $validated['captcha_site_key'] = trim((string) ($validated['captcha_site_key'] ?? ''));

        SecuritySettings::save($validated, $newSecret, $removeSecret);

        return $this->back('Security settings saved.');
    }

    public function block(Request $request)
    {
        Gate::authorize('admin.settings.security.update');

        $validated = $request->validate([
            'ip' => ['required', 'string', 'max:64', function ($attribute, $value, $fail) {
                if (! Firewall::validRule($value)) {
                    $fail('Enter an IP address like 203.0.113.7, or a range like 203.0.113.0/24.');
                }
            }],
            'reason' => ['nullable', 'string', 'max:200'],
            'duration' => ['required', Rule::in(['60', '1440', '10080', 'forever'])],
        ]);

        $ip = trim($validated['ip']);

        if (Firewall::matches((string) $request->ip(), $ip)) {
            return back()->withInput()->withErrors(['ip' => "That would block you too (your IP is {$request->ip()})."]);
        }

        if (IpBlock::active()->where('ip', $ip)->exists()) {
            return back()->withInput()->withErrors(['ip' => "{$ip} is already blocked."]);
        }

        $this->firewall->block($ip, $validated['reason'] ?: null, $validated['duration'] === 'forever' ? null : (int) $validated['duration'], $request->user());

        return $this->back("{$ip} is blocked.");
    }

    public function unblock(IpBlock $block)
    {
        Gate::authorize('admin.settings.security.update');

        $this->firewall->unblock($block);

        return $this->back("{$block->ip} is unblocked.");
    }

    public function clearLog()
    {
        Gate::authorize('admin.settings.security.update');

        $count = BlockedRequest::query()->delete();

        return $this->back("Cleared {$count} blocked ".str('request')->plural($count).' from the log.');
    }

    private function back(string $message)
    {
        return to_route('admin.settings.index', ['tab' => 'security'])->with('success', $message);
    }
}
