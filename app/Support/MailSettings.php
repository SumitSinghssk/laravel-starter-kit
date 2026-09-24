<?php

namespace App\Support;

use App\Helpers\Settings;
use App\Mail\SmtpTestMail;
use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailSettings
{
    public const KEY = 'mail_settings';

    public const ENCRYPTIONS = [
        'tls' => 'TLS (STARTTLS)',
        'ssl' => 'SSL',
        'none' => 'None',
    ];

    public const PRESETS = [
        'custom' => ['label' => 'Other / custom', 'host' => '', 'port' => 587, 'encryption' => 'tls', 'note' => 'Enter the SMTP details your email provider or host gives you.'],
        'gmail' => ['label' => 'Gmail / Google Workspace', 'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls', 'note' => 'Use your full Gmail address as the username and an App Password (Google Account → Security → App passwords), not your normal password.'],
        'outlook' => ['label' => 'Outlook / Microsoft 365', 'host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls', 'note' => 'Use your full email address. SMTP sending (SMTP AUTH) must be allowed for the mailbox in Microsoft 365.'],
        'zoho' => ['label' => 'Zoho Mail', 'host' => 'smtp.zoho.com', 'port' => 587, 'encryption' => 'tls', 'note' => 'Accounts in India use smtp.zoho.in, in Europe smtp.zoho.eu. With two-factor login, use an app-specific password.'],
        'yahoo' => ['label' => 'Yahoo Mail', 'host' => 'smtp.mail.yahoo.com', 'port' => 465, 'encryption' => 'ssl', 'note' => 'Create an app password in Yahoo Account Security and use it here.'],
        'sendgrid' => ['label' => 'SendGrid', 'host' => 'smtp.sendgrid.net', 'port' => 587, 'encryption' => 'tls', 'note' => 'The username is literally “apikey”; the password is your SendGrid API key. The From address must be a verified sender.'],
        'mailgun' => ['label' => 'Mailgun', 'host' => 'smtp.mailgun.org', 'port' => 587, 'encryption' => 'tls', 'note' => 'Use the SMTP login and password from your Mailgun domain settings. EU accounts use smtp.eu.mailgun.org.'],
        'ses' => ['label' => 'Amazon SES', 'host' => 'email-smtp.us-east-1.amazonaws.com', 'port' => 587, 'encryption' => 'tls', 'note' => 'Change the region in the host to yours, and use SMTP credentials created in the SES console (not your AWS keys).'],
        'brevo' => ['label' => 'Brevo (Sendinblue)', 'host' => 'smtp-relay.brevo.com', 'port' => 587, 'encryption' => 'tls', 'note' => 'Use the SMTP login and key from Brevo → SMTP & API.'],
        'postmark' => ['label' => 'Postmark', 'host' => 'smtp.postmarkapp.com', 'port' => 587, 'encryption' => 'tls', 'note' => 'Use your Server API token as both the username and the password.'],
        'hostinger' => ['label' => 'Hostinger', 'host' => 'smtp.hostinger.com', 'port' => 465, 'encryption' => 'ssl', 'note' => 'Use the full email address and its mailbox password.'],
        'godaddy' => ['label' => 'GoDaddy', 'host' => 'smtpout.secureserver.net', 'port' => 465, 'encryption' => 'ssl', 'note' => 'Use the full email address and its mailbox password.'],
        'mailtrap' => ['label' => 'Mailtrap (testing)', 'host' => 'sandbox.smtp.mailtrap.io', 'port' => 2525, 'encryption' => 'tls', 'note' => 'Catches every email in a Mailtrap inbox instead of delivering it. Good for testing.'],
    ];

    public const DEFAULTS = [
        'enabled' => false,
        'provider' => 'custom',
        'host' => '',
        'port' => 587,
        'encryption' => 'tls',
        'username' => '',
        'password' => null,
        'from_address' => '',
        'from_name' => '',
        'reply_to' => '',
    ];

    public static function settings(): array
    {
        $stored = Settings::get(self::KEY);

        return [...self::DEFAULTS, ...(is_array($stored) ? $stored : [])];
    }

    public static function forForm(): array
    {
        $settings = self::settings();
        $settings['has_password'] = filled($settings['password']);
        unset($settings['password']);

        return $settings;
    }

    public static function password(): ?string
    {
        $encrypted = self::settings()['password'];

        if (blank($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }
    }

    public static function save(array $values, ?string $newPassword, bool $removePassword): void
    {
        $current = self::settings();

        $password = match (true) {
            $removePassword => null,
            filled($newPassword) => Crypt::encryptString($newPassword),
            default => $current['password'],
        };

        Setting::updateOrCreate(['key' => self::KEY], ['value' => [...$current, ...$values, 'password' => $password]]);
        Settings::flush();
    }

    private static ?string $fallbackMailer = null;

    public static function fallbackMailer(): string
    {
        return self::$fallbackMailer ?? (string) config('mail.default');
    }

    public static function apply(): void
    {
        self::$fallbackMailer ??= (string) config('mail.default');
        $settings = self::settings();

        if (! $settings['enabled'] || blank($settings['host'])) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp' => [...config('mail.mailers.smtp', []), ...self::transport($settings, self::password())],
            'mail.from.address' => $settings['from_address'] ?: config('mail.from.address'),
            'mail.from.name' => $settings['from_name'] ?: config('mail.from.name'),
        ]);

        if (filled($settings['reply_to'])) {
            config(['mail.reply_to' => ['address' => $settings['reply_to'], 'name' => $settings['from_name'] ?: null]]);
        }
    }

    public static function canDeliver(): bool
    {
        $mailer = config('mail.default');

        return ! in_array(config("mail.mailers.{$mailer}.transport", $mailer), ['log', 'array'], true);
    }

    public static function transport(array $values, ?string $password): array
    {
        $encryption = $values['encryption'] ?? 'tls';

        return [
            'transport' => 'smtp',
            'url' => null,
            'scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
            'host' => $values['host'],
            'port' => (int) $values['port'],
            'username' => filled($values['username'] ?? null) ? $values['username'] : null,
            'password' => filled($password) ? $password : null,
            'timeout' => 15,
            'auto_tls' => $encryption !== 'none',
            'require_tls' => $encryption === 'tls',
            'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
        ];
    }

    public static function sendTest(array $values, ?string $password, string $to): array
    {
        $started = microtime(true);

        try {
            $mailer = Mail::build(self::transport($values, $password));
            $mailer->alwaysFrom($values['from_address'], $values['from_name'] ?: null);
            if (filled($values['reply_to'] ?? null)) {
                $mailer->alwaysReplyTo($values['reply_to']);
            }

            $mailer->to($to)->send(new SmtpTestMail($values));

            return ['ok' => true, 'message' => "Test email sent to {$to}. Check the inbox, and the spam folder if it isn't there in a minute.", 'seconds' => round(microtime(true) - $started, 1)];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => self::explain($e->getMessage(), $values), 'detail' => self::clean($e->getMessage()), 'seconds' => round(microtime(true) - $started, 1)];
        }
    }

    public static function explain(string $error, array $values): string
    {
        $error = strtolower($error);
        $server = ($values['host'] ?? '').':'.($values['port'] ?? '');

        return match (true) {
            str_contains($error, 'authenticat') || str_contains($error, '535') || str_contains($error, 'username and password not accepted') || str_contains($error, 'invalid login') => 'The mail server refused the username or password. Gmail, Yahoo and Zoho need an App Password instead of your normal one; Outlook / Microsoft 365 needs SMTP AUTH allowed for the mailbox.',
            str_contains($error, 'getaddrinfo') || str_contains($error, 'name or service not known') || str_contains($error, 'no such host') || str_contains($error, 'php_network_getaddresses') => "The host “{$values['host']}” could not be found. Check the spelling of the SMTP host.",
            str_contains($error, 'timed out') || str_contains($error, 'timeout') => "No answer from {$server}. The port may be blocked by your hosting provider or firewall: try 587 with TLS or 465 with SSL, or ask your host to allow outgoing SMTP.",
            str_contains($error, 'connection refused') || str_contains($error, 'could not be established') || str_contains($error, 'unable to connect') => "Could not connect to {$server}. Check the host and port. Many hosts block outgoing port 25; try 587 (TLS) or 465 (SSL).",
            str_contains($error, 'starttls') || str_contains($error, '530') => 'The server wants an encrypted connection. Choose TLS (port 587) or SSL (port 465).',
            str_contains($error, 'certificate') || str_contains($error, 'ssl') || str_contains($error, 'tls') || str_contains($error, 'crypto') => 'The secure connection failed. Try the other encryption: SSL with port 465, or TLS with port 587.',
            str_contains($error, 'sender') || str_contains($error, '553') || str_contains($error, '550') || str_contains($error, '554') || str_contains($error, 'not owned') || str_contains($error, 'not verified') || str_contains($error, 'from address') => 'The server refused the From address. Send from an address this account may use (often the same as the username), or verify it with your email provider.',
            default => 'The email could not be sent. See the server’s reply below.',
        };
    }

    private static function clean(string $message): string
    {
        return mb_strimwidth(trim(preg_replace('/\s+/', ' ', $message)), 0, 500, '…');
    }
}
