<?php

namespace App\Support;

use App\Helpers\Settings;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class EmailTemplates
{
    public const KEY = 'email_templates';

    public const DESIGN_KEY = 'email_design';

    public const FIELDS = ['subject', 'heading', 'body', 'button', 'note'];

    public const GROUPS = [
        'account' => 'Sign-in & passwords',
        'two_factor' => 'Two-factor sign-in',
        'enquiries' => 'Enquiries',
    ];

    public const DESIGN_DEFAULTS = [
        'accent' => '#2563eb',
        'footer' => 'This is an automatic message.',
        'show_logo' => true,
    ];

    private const COMMON = [
        'name' => ['Full name of the person', 'Priya Sharma'],
        'first_name' => ['First name', 'Priya'],
        'email' => ['Their email address', 'priya@example.com'],
        'app_name' => ['Website name', null],
        'site_url' => ['Website address', null],
    ];

    private const DEVICE = [
        'when' => ['Date and time it happened', null],
        'device' => ['Browser and system', 'Chrome on Windows'],
        'ip' => ['IP address', '203.0.113.24'],
    ];

    public static function definitions(): array
    {
        $twoFactor = fn (string $label, string $description, string $heading, string $text, bool $warn) => [
            'group' => 'two_factor',
            'label' => $label,
            'description' => $description,
            'optional' => true,
            'fields' => ['subject', 'heading', 'body', 'note'],
            'placeholders' => self::COMMON + self::DEVICE + ['done_by' => ['Admin who did it (if any)', 'Rahul Verma']],
            'defaults' => [
                'subject' => $heading.' · {app_name}',
                'heading' => $heading,
                'body' => "Hi {first_name}, {$text}",
                'note' => $warn ? "Wasn't you? Change your password straight away and tell your site administrator." : '',
            ],
            'warn' => $warn,
        ];

        return [
            'password_reset' => [
                'group' => 'account',
                'label' => 'Password reset link',
                'description' => 'Sent when someone uses “Forgot password” on the admin sign-in page.',
                'optional' => false,
                'fields' => ['subject', 'heading', 'body', 'button', 'note'],
                'placeholders' => self::COMMON + Arr::except(self::DEVICE, 'when') + ['minutes' => ['Minutes before the link expires', '60']],
                'defaults' => [
                    'subject' => 'Reset your {app_name} password',
                    'heading' => 'Reset your password',
                    'body' => 'Hi {first_name}, someone asked to reset the password for your {app_name} admin account ({email}). Click the button to choose a new one.',
                    'button' => 'Choose a new password',
                    'note' => "The link works once and expires in {minutes} minutes.\n\nDidn't ask for this? You can ignore this email. Your password stays the same until someone uses the link.",
                ],
                'warn' => false,
            ],
            'password_changed' => [
                'group' => 'account',
                'label' => 'Password changed',
                'description' => 'Confirms a password reset and warns the owner in case it was not them.',
                'optional' => true,
                'fields' => ['subject', 'heading', 'body', 'note'],
                'placeholders' => self::COMMON + self::DEVICE,
                'defaults' => [
                    'subject' => 'Your {app_name} password was changed',
                    'heading' => 'Your password was changed',
                    'body' => 'Hi {first_name}, the password for your {app_name} admin account ({email}) was just reset. For safety, you were signed out on every device.',
                    'note' => "Wasn't you? Reset your password again straight away and tell your site administrator.",
                ],
                'warn' => true,
            ],
            'account_locked' => [
                'group' => 'account',
                'label' => 'Account locked',
                'description' => 'Sent when an account is locked after too many failed sign-in attempts.',
                'optional' => true,
                'fields' => ['subject', 'heading', 'body', 'button', 'note'],
                'placeholders' => self::COMMON + self::DEVICE + [
                    'failures' => ['Number of failed attempts', '5'],
                    'unlock_info' => ['When it unlocks, as a sentence', null],
                ],
                'defaults' => [
                    'subject' => 'Your {app_name} account was locked',
                    'heading' => 'Your account was locked',
                    'body' => 'Hi {first_name}, there were {failures} failed attempts to sign in to your {app_name} admin account ({email}), so we locked it to keep it safe. {unlock_info}',
                    'button' => 'Reset my password',
                    'note' => "Resetting your password unlocks the account straight away.\n\nIf it wasn't you, someone may be guessing your password, so choose a strong new one.",
                ],
                'warn' => false,
            ],
            'two_factor_enabled' => $twoFactor('Two-factor turned on', 'Sent when someone sets up two-factor sign-in.', 'Two-factor sign-in is on', 'two-factor sign-in was turned on for your account. From now on you need a code from your authenticator app when you sign in.', false),
            'two_factor_changed' => $twoFactor('Two-factor moved to a new app', 'Sent when two-factor sign-in is set up again with another app.', 'Two-factor sign-in moved to a new app', 'two-factor sign-in was set up again with a new authenticator app. Codes from the old app no longer work.', true),
            'two_factor_disabled' => $twoFactor('Two-factor turned off', 'Sent when someone turns off their own two-factor sign-in.', 'Two-factor sign-in was turned off', 'two-factor sign-in was turned off for your account. Only your password is needed to sign in now.', true),
            'two_factor_reset' => $twoFactor('Two-factor reset by an admin', 'Sent when an administrator resets someone’s two-factor sign-in.', 'Your two-factor sign-in was reset', 'an administrator reset two-factor sign-in on your account. You can set it up again with your authenticator app.', true),
            'two_factor_recovery' => $twoFactor('Recovery code used', 'Sent when someone signs in with a recovery code.', 'A recovery code was used', 'someone signed in to your account with one of your recovery codes instead of a code from your app. Each recovery code works only once.', true),
            'enquiry_reply' => [
                'group' => 'enquiries',
                'label' => 'Enquiry reply',
                'description' => 'The starting text when you reply to an enquiry. You can still change it before each reply.',
                'optional' => false,
                'fields' => ['subject', 'body', 'note'],
                'placeholders' => self::COMMON + [
                    'enquiry_subject' => ['Subject of the enquiry', 'Question about pricing'],
                    'reference' => ['Enquiry reference', 'ENQ-000042'],
                    'sender_name' => ['Your name (the admin replying)', null],
                ],
                'defaults' => [
                    'subject' => 'Re: {enquiry_subject}',
                    'body' => "Hi {first_name},\n\n\n\nBest regards,\n{sender_name}\n{app_name}",
                    'note' => 'Reply to this email to answer {sender_name} directly. Reference {reference}.',
                ],
                'warn' => false,
            ],
        ];
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::definitions());
    }

    public static function definition(string $key): array
    {
        return self::definitions()[$key] ?? throw new \InvalidArgumentException("Unknown email template [{$key}].");
    }

    public static function stored(): array
    {
        return (array) Settings::get(self::KEY, []);
    }

    public static function custom(string $key): ?array
    {
        $row = self::stored()[$key] ?? null;

        return is_array($row) ? $row : null;
    }

    public static function values(string $key): array
    {
        $definition = self::definition($key);
        $custom = self::custom($key) ?? [];
        $values = [];

        foreach ($definition['fields'] as $field) {
            $values[$field] = array_key_exists($field, $custom) ? (string) $custom[$field] : (string) ($definition['defaults'][$field] ?? '');
        }

        return $values;
    }

    public static function enabled(string $key): bool
    {
        if (! self::definition($key)['optional']) {
            return true;
        }

        return (bool) (self::custom($key)['enabled'] ?? true);
    }

    public static function isCustomised(string $key): bool
    {
        $custom = self::custom($key);

        if (! $custom) {
            return false;
        }

        return self::values($key) !== self::defaults($key) || ! self::enabled($key);
    }

    public static function defaults(string $key): array
    {
        $definition = self::definition($key);

        return collect($definition['fields'])->mapWithKeys(fn ($field) => [$field => (string) ($definition['defaults'][$field] ?? '')])->all();
    }

    public static function save(string $key, array $values, bool $enabled, ?User $by): void
    {
        $definition = self::definition($key);
        $stored = self::stored();

        $stored[$key] = [
            ...Arr::only($values, $definition['fields']),
            'enabled' => $definition['optional'] ? $enabled : true,
            'updated_at' => now()->toIso8601String(),
            'updated_by' => $by?->name,
        ];

        self::write(self::KEY, $stored);
    }

    public static function restore(string $key): void
    {
        $stored = self::stored();
        unset($stored[$key]);

        self::write(self::KEY, $stored);
    }

    public static function design(): array
    {
        $saved = (array) Settings::get(self::DESIGN_KEY, []);

        return [...self::DESIGN_DEFAULTS, ...Arr::only($saved, array_keys(self::DESIGN_DEFAULTS))];
    }

    public static function saveDesign(array $design): void
    {
        self::write(self::DESIGN_KEY, Arr::only($design, array_keys(self::DESIGN_DEFAULTS)));
    }

    public static function unknownPlaceholders(string $key, string $text): array
    {
        preg_match_all('/\{\s*([a-z_]+)\s*\}/i', $text, $matches);

        return collect($matches[1])
            ->map(fn ($name) => strtolower($name))
            ->unique()
            ->reject(fn ($name) => array_key_exists($name, self::definition($key)['placeholders']))
            ->values()
            ->all();
    }

    public static function sampleVariables(string $key, ?User $viewer = null): array
    {
        $variables = ['app_name' => Settings::appName(), 'site_url' => url('/')];

        foreach (self::definition($key)['placeholders'] as $name => [, $sample]) {
            if ($sample !== null) {
                $variables[$name] = $sample;
            }
        }

        return [
            ...$variables,
            'when' => LocalTime::dateTime(now(), true),
            'unlock_info' => 'It unlocks by itself at '.LocalTime::dateTime(now()->addMinutes(15), true).'.',
            'sender_name' => $viewer?->name ?? 'Rahul Verma',
        ];
    }

    public static function variables(array $variables): array
    {
        $name = trim((string) ($variables['name'] ?? ''));

        return [
            'app_name' => Settings::appName(),
            'site_url' => url('/'),
            'first_name' => $name !== '' ? Str::before($name, ' ') : 'there',
            ...$variables,
        ];
    }

    public static function fill(string $text, array $variables): string
    {
        return preg_replace_callback('/\{\s*([a-z_]+)\s*\}/i', function ($match) use ($variables) {
            $name = strtolower($match[1]);

            return array_key_exists($name, $variables) ? (string) $variables[$name] : $match[0];
        }, $text);
    }

    public static function toHtml(string $text, array $variables, string $accent): HtmlString
    {
        $paragraphs = preg_split('/\R{2,}/', trim($text)) ?: [];

        $html = collect($paragraphs)
            ->map(fn ($paragraph) => trim($paragraph))
            ->filter(fn ($paragraph) => $paragraph !== '')
            ->map(fn ($paragraph) => self::formatParagraph($paragraph, $variables, $accent))
            ->all();

        return new HtmlString(implode('', $html));
    }

    public static function render(string $key, array $variables, array $overrides = []): array
    {
        $definition = self::definition($key);
        $values = [...self::values($key), ...Arr::only($overrides, $definition['fields'])];
        $variables = self::variables($variables);
        $design = self::design();

        return [
            'subject' => Str::squish(self::fill($values['subject'] ?? '', $variables)),
            'heading' => Str::squish(self::fill($values['heading'] ?? '', $variables)),
            'body' => self::toHtml($values['body'] ?? '', $variables, $design['accent']),
            'bodyText' => self::plain(self::fill($values['body'] ?? '', $variables)),
            'button' => Str::squish(self::fill($values['button'] ?? '', $variables)),
            'note' => self::toHtml($values['note'] ?? '', $variables, $design['accent']),
            'noteText' => self::plain(self::fill($values['note'] ?? '', $variables)),
            'warn' => $definition['warn'],
            'design' => $design,
            'logo' => $design['show_logo'] ? Settings::logoLight() : null,
            'appName' => Settings::appName(),
        ];
    }

    public static function compose(string $key, array $variables, array $details = [], ?string $url = null, array $overrides = []): array
    {
        $rendered = self::render($key, $variables, $overrides);

        return [
            'subject' => $rendered['subject'],
            'view' => 'emails.template',
            'text' => 'emails.template-text',
            'data' => [...$rendered, 'details' => array_filter($details, fn ($value) => filled($value)), 'url' => $url],
        ];
    }

    public static function composeReply(array $variables, string $subject, string $body, ?string $original, ?string $receivedAt, array $overrides = []): array
    {
        $rendered = self::render('enquiry_reply', $variables, $overrides);

        return [
            'subject' => $subject,
            'view' => 'emails.enquiry-reply',
            'text' => 'emails.enquiry-reply-text',
            'data' => [
                'appName' => $rendered['appName'],
                'design' => $rendered['design'],
                'logo' => $rendered['logo'],
                'body' => $body,
                'original' => $original,
                'receivedAt' => $receivedAt,
                'footer' => $rendered['noteText'],
            ],
        ];
    }

    public static function preview(string $key, array $overrides = [], ?User $viewer = null): array
    {
        $variables = self::sampleVariables($key, $viewer);

        if ($key === 'enquiry_reply') {
            $rendered = self::render($key, $variables, $overrides);
            $body = str_replace("\n\n\n\n", "\n\nThanks for getting in touch. Our plans start at ₹999 a month and every plan includes a free 14-day trial.\n\n", $rendered['bodyText']);

            return self::composeReply($variables, $rendered['subject'], $body, "Hello, could you tell me more about your pricing?\n\nThanks,\nPriya", LocalTime::date(now()->subDay()), $overrides);
        }

        return self::compose($key, $variables, self::sampleDetails($key, $variables), in_array('button', self::definition($key)['fields'], true) ? route('admin.password.request') : null, $overrides);
    }

    public static function sampleDetails(string $key, array $variables): array
    {
        return match ($key) {
            'password_reset' => ['Requested from' => $variables['device'], 'IP address' => $variables['ip']],
            'account_locked' => ['When' => $variables['when'], 'Last attempt from' => $variables['device'], 'IP address' => $variables['ip']],
            'password_changed' => ['When' => $variables['when'], 'Device' => $variables['device'], 'IP address' => $variables['ip']],
            default => str_starts_with($key, 'two_factor')
                ? ['Account' => $variables['email'], 'When' => $variables['when'], 'Done by' => $key === 'two_factor_reset' ? $variables['done_by'] : null, 'Device' => $variables['device'], 'IP address' => $variables['ip']]
                : [],
        };
    }

    public static function forget(): void
    {
        Settings::flush();
    }

    private static function formatParagraph(string $paragraph, array $variables, string $accent): string
    {
        $placeholders = [];
        $marked = preg_replace_callback('/\{\s*([a-z_]+)\s*\}/i', function ($match) use ($variables, &$placeholders) {
            $name = strtolower($match[1]);

            if (! array_key_exists($name, $variables)) {
                return $match[0];
            }

            $token = "\x01".count($placeholders)."\x02";
            $placeholders[$token] = e((string) $variables[$name]);

            return $token;
        }, $paragraph);

        $html = e($marked);
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
        $html = strtr($html, $placeholders);
        $html = preg_replace_callback(
            '~(?<![="\w/])(https?://[^\s<"]+[^\s<".,;:!?)\]])~i',
            fn ($match) => '<a href="'.$match[1].'" style="color: '.e($accent).'; word-break: break-all">'.$match[1].'</a>',
            $html,
        );
        $html = nl2br($html, false);

        return '<p style="margin: 0 0 14px">'.$html.'</p>';
    }

    private static function plain(string $text): string
    {
        return trim(preg_replace('/\*\*(.+?)\*\*/s', '$1', $text));
    }

    private static function write(string $key, array $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        Settings::flush();
    }
}
