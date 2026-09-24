<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use App\Support\MailSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MailSettingController extends Controller
{
    public function update(Request $request)
    {
        Gate::authorize('admin.settings.email.update');

        $data = $request->validate($this->rules($request->boolean('enabled')), $this->messages());

        MailSettings::save($this->values($data), $data['password'] ?? null, $request->boolean('remove_password'));

        return to_route('admin.settings.index', ['tab' => 'email'])->with('success', $request->boolean('enabled')
            ? 'Email settings saved. The website now sends email through '.$data['host'].'.'
            : 'Email settings saved. Sending through them is switched off.');
    }

    public function test(Request $request)
    {
        Gate::authorize('admin.settings.email.update');

        $data = $request->validate([
            ...$this->rules(true),
            'test_email' => ['required', 'email:rfc', 'max:255'],
        ], $this->messages() + ['test_email.required' => 'Enter the email address to send the test to.']);

        $password = match (true) {
            $request->boolean('remove_password') => null,
            filled($data['password'] ?? null) => $data['password'],
            default => MailSettings::password(),
        };

        $result = MailSettings::sendTest($this->values($data), $password, $data['test_email']);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    private function rules(bool $enabled): array
    {
        $required = $enabled ? 'required' : 'nullable';

        return [
            'enabled' => ['boolean'],
            'provider' => ['required', Rule::in(array_keys(MailSettings::PRESETS))],
            'host' => [$required, 'string', 'max:255', 'regex:/^[A-Za-z0-9.-]+$/'],
            'port' => [$required, 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(array_keys(MailSettings::ENCRYPTIONS))],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:500'],
            'from_address' => [$required, 'email:rfc', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:120'],
            'reply_to' => ['nullable', 'email:rfc', 'max:255'],
        ];
    }

    private function messages(): array
    {
        return [
            'host.required' => 'Enter the SMTP host, e.g. smtp.gmail.com.',
            'host.regex' => 'Enter only the host name, e.g. smtp.gmail.com (no https:// or port).',
            'port.required' => 'Enter the port, usually 587 or 465.',
            'from_address.required' => 'Enter the address emails are sent from.',
        ];
    }

    private function values(array $data): array
    {
        return [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'provider' => $data['provider'],
            'host' => strtolower(trim((string) ($data['host'] ?? ''))),
            'port' => (int) ($data['port'] ?? 587),
            'encryption' => $data['encryption'],
            'username' => trim((string) ($data['username'] ?? '')),
            'from_address' => trim((string) ($data['from_address'] ?? '')),
            'from_name' => trim((string) ($data['from_name'] ?? '')),
            'reply_to' => trim((string) ($data['reply_to'] ?? '')),
        ];
    }
}
