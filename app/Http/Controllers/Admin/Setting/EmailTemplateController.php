<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Controller;
use App\Mail\TemplateTestMail;
use App\Support\EmailTemplates;
use App\Support\MailSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Validator;
use Throwable;

class EmailTemplateController extends Controller
{
    private const LIMITS = ['subject' => 200, 'heading' => 200, 'body' => 5000, 'button' => 60, 'note' => 2000];

    public function update(Request $request, string $template)
    {
        Gate::authorize('admin.settings.email-templates.update');
        $definition = $this->definition($template);

        $validated = $request->validateWithBag('template', $this->rules($definition, true), $this->messages());
        $validator = validator($validated);
        $this->checkPlaceholders($validator, $template, $validated);
        $validator->validateWithBag('template');

        EmailTemplates::save($template, $this->values($definition, $validated), $request->boolean('enabled', true), $request->user());

        return to_route('admin.settings.index', ['tab' => 'email-templates', 'template' => $template])
            ->with('success', "“{$definition['label']}” email saved. It is used from the next email on.");
    }

    public function destroy(string $template)
    {
        Gate::authorize('admin.settings.email-templates.update');
        $definition = $this->definition($template);

        EmailTemplates::restore($template);

        return to_route('admin.settings.index', ['tab' => 'email-templates', 'template' => $template])
            ->with('success', "“{$definition['label']}” is back to the original text.");
    }

    public function preview(Request $request, string $template)
    {
        Gate::authorize('admin.settings.email-templates.view');
        $definition = $this->definition($template);

        $validated = $request->validate($this->rules($definition, false));
        $composed = EmailTemplates::preview($template, $this->values($definition, $validated), $request->user());

        return response()->json([
            'subject' => $composed['subject'],
            'html' => view($composed['view'], $composed['data'])->render(),
            'unknown' => EmailTemplates::unknownPlaceholders($template, implode(' ', $this->values($definition, $validated))),
        ]);
    }

    public function test(Request $request, string $template)
    {
        Gate::authorize('admin.settings.email-templates.update');
        $definition = $this->definition($template);

        $validated = $request->validate([
            ...$this->rules($definition, true),
            'test_email' => ['required', 'email:rfc', 'max:255'],
        ], [...$this->messages(), 'test_email.required' => 'Enter the email address to send the test to.']);

        $composed = EmailTemplates::preview($template, $this->values($definition, $validated), $request->user());

        try {
            Mail::to($validated['test_email'])->send(new TemplateTestMail($composed));
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'The test email could not be sent. '.MailSettings::explain($e->getMessage(), (array) config('mail.mailers.'.config('mail.default'))),
            ], 422);
        }

        return response()->json(MailSettings::canDeliver()
            ? ['ok' => true, 'message' => "Test sent to {$validated['test_email']} with example details. It may take a minute to arrive."]
            : ['ok' => false, 'message' => 'Email sending is not set up yet, so the test was only written to the log file. Set it up in Settings → Email (SMTP).']);
    }

    public function design(Request $request)
    {
        Gate::authorize('admin.settings.email-templates.update');

        $validated = $request->validateWithBag('design', [
            'accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'footer' => ['nullable', 'string', 'max:200'],
            'show_logo' => ['boolean'],
        ], ['accent.regex' => 'Pick a colour like #2563eb.']);

        EmailTemplates::saveDesign([
            'accent' => strtolower($validated['accent']),
            'footer' => trim((string) ($validated['footer'] ?? '')),
            'show_logo' => $request->boolean('show_logo'),
        ]);

        return to_route('admin.settings.index', ['tab' => 'email-templates'])->with('success', 'Email look saved. Every email uses it from now on.');
    }

    private function definition(string $template): array
    {
        abort_unless(EmailTemplates::exists($template), 404);

        return EmailTemplates::definition($template);
    }

    private function rules(array $definition, bool $strict): array
    {
        $rules = ['enabled' => ['boolean']];

        foreach ($definition['fields'] as $field) {
            $required = $strict && in_array($field, ['subject', 'body', 'button'], true) ? 'required' : 'nullable';
            $rules[$field] = [$required, 'string', 'max:'.self::LIMITS[$field]];
        }

        return $rules;
    }

    private function messages(): array
    {
        return [
            'subject.required' => 'The email needs a subject.',
            'body.required' => 'The email needs some text.',
            'button.required' => 'Give the button a label, e.g. “Choose a new password”.',
        ];
    }

    private function values(array $definition, array $validated): array
    {
        return collect($definition['fields'])
            ->mapWithKeys(fn ($field) => [$field => str_replace("\r\n", "\n", trim((string) ($validated[$field] ?? '')))])
            ->all();
    }

    private function checkPlaceholders(Validator $validator, string $template, array $validated): void
    {
        $validator->after(function (Validator $validator) use ($template, $validated) {
            foreach (EmailTemplates::FIELDS as $field) {
                $unknown = EmailTemplates::unknownPlaceholders($template, (string) ($validated[$field] ?? ''));

                if ($unknown) {
                    $list = collect($unknown)->map(fn ($name) => '{'.$name.'}')->join(', ', ' and ');
                    $validator->errors()->add($field, "{$list} can't be filled in for this email. Use one of the placeholders listed next to the editor.");
                }
            }
        });
    }
}
