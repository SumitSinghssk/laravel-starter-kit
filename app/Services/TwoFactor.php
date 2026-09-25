<?php

namespace App\Services;

use App\Helpers\Settings;
use App\Mail\TwoFactorNoticeMail;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\EmailTemplates;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

class TwoFactor
{
    public const PENDING_LOGIN = 'two_factor_login';

    public const PENDING_SECRET = 'two_factor_pending_secret';

    public const NEW_CODES = 'two_factor_new_codes';

    public const TRUST_COOKIE = 'admin_2fa_trusted';

    public const TRUST_DAYS = 30;

    public const RECOVERY_CODES = 8;

    public const LOGIN_MINUTES = 10;

    public function __construct(private Google2FA $google2fa) {}

    public function newSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function pendingSecret(Request $request): ?string
    {
        $stored = $request->session()->get(self::PENDING_SECRET);

        return $stored ? rescue(fn () => Crypt::decryptString($stored), null, false) : null;
    }

    public function startSetup(Request $request): string
    {
        $secret = $this->newSecret();
        $request->session()->put(self::PENDING_SECRET, Crypt::encryptString($secret));

        return $secret;
    }

    public function cancelSetup(Request $request): void
    {
        $request->session()->forget(self::PENDING_SECRET);
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(Settings::appName(), $user->email, $secret);
    }

    public function qrSvg(User $user, string $secret): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))->writeString($this->otpauthUrl($user, $secret));

        return trim(Str::after($svg, '?>'));
    }

    public static function formatSecret(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    public function checkCode(string $secret, string $code, ?int $userId = null): bool
    {
        $code = preg_replace('/\D+/', '', $code);

        if (strlen($code) !== 6) {
            return false;
        }

        $key = 'two-factor:last-step:'.($userId ?? md5($secret));
        $step = $this->google2fa->verifyKeyNewer($secret, $code, Cache::get($key), 1);

        if ($step === false) {
            return false;
        }

        Cache::put($key, $step === true ? $this->google2fa->getTimestamp() : $step, now()->addMinutes(5));

        return true;
    }

    public function confirm(Request $request, User $user, string $code): ?array
    {
        $secret = $this->pendingSecret($request);

        if (! $secret || ! $this->checkCode($secret, $code)) {
            return null;
        }

        $codes = $this->makeRecoveryCodes();
        $wasOn = $user->hasTwoFactor();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn ($code) => $this->hashCode($code), $codes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->cancelSetup($request);
        $this->log($user, $wasOn ? 'two_factor_changed' : 'two_factor_enabled', $wasOn ? 'Moved two-factor sign-in to a new authenticator app' : 'Turned on two-factor sign-in', $request);
        $this->notify($user, $wasOn ? 'changed' : 'enabled', $request);

        return $codes;
    }

    public function regenerateRecoveryCodes(Request $request, User $user): array
    {
        $codes = $this->makeRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => array_map(fn ($code) => $this->hashCode($code), $codes)])->save();

        $this->log($user, 'two_factor_codes', 'Made new recovery codes; the old ones stopped working', $request);

        return $codes;
    }

    public function useRecoveryCode(User $user, string $code): bool
    {
        $hash = $this->hashCode($code);
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $stored) {
            if (hash_equals($stored, $hash)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    public function remainingCodes(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    public function disable(Request $request, User $user, ?User $by = null): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        Cache::forget('two-factor:last-step:'.$user->id);

        if ($by && ! $by->is($user)) {
            $this->log($user, 'two_factor_reset', "Two-factor sign-in was reset by {$by->name}", $request, $by);
            $this->notify($user, 'reset', $request, $by);
        } else {
            $this->log($user, 'two_factor_disabled', 'Turned off two-factor sign-in', $request);
            $this->notify($user, 'disabled', $request);
        }
    }

    public function trustCookie(User $user): Cookie
    {
        $expires = now()->addDays(self::TRUST_DAYS)->timestamp;

        return cookie(self::TRUST_COOKIE, $user->id.'|'.$expires.'|'.$this->trustSignature($user, $expires), self::TRUST_DAYS * 24 * 60, null, null, null, true, false, 'lax');
    }

    public function trusts(Request $request, User $user): bool
    {
        $parts = explode('|', (string) $request->cookie(self::TRUST_COOKIE));

        if (count($parts) !== 3 || (int) $parts[0] !== $user->id || (int) $parts[1] < now()->timestamp) {
            return false;
        }

        return hash_equals($this->trustSignature($user, (int) $parts[1]), $parts[2]);
    }

    public function startLogin(Request $request, User $user, bool $remember): void
    {
        $request->session()->put(self::PENDING_LOGIN, [
            'id' => $user->id,
            'remember' => $remember,
            'expires' => now()->addMinutes(self::LOGIN_MINUTES)->timestamp,
        ]);
    }

    public function pendingLogin(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_LOGIN);

        if (! is_array($pending) || ($pending['expires'] ?? 0) < now()->timestamp) {
            $request->session()->forget(self::PENDING_LOGIN);

            return null;
        }

        $user = User::find($pending['id']);

        return $user && $user->hasTwoFactor() ? [...$pending, 'user' => $user] : null;
    }

    public function log(User $user, string $action, string $description, Request $request, ?User $by = null, bool $suspicious = false): void
    {
        rescue(fn () => ActivityLog::create([
            'user_id' => ($by ?? $user)->id,
            'action' => $action,
            'model_type' => User::class,
            'model_id' => $user->id,
            'model_name' => $user->name,
            'email' => $user->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'description' => $description,
            'is_suspicious' => $suspicious,
        ]), report: false);
    }

    public function notify(User $user, string $event, Request $request, ?User $by = null): void
    {
        if (! EmailTemplates::enabled(TwoFactorNoticeMail::templateFor($event))) {
            return;
        }

        try {
            Mail::to($user)->send(new TwoFactorNoticeMail($user, $event, $request->ip(), $request->userAgent(), $by));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function makeRecoveryCodes(): array
    {
        return collect(range(1, self::RECOVERY_CODES))
            ->map(fn () => Str::lower(Str::random(5)).'-'.Str::lower(Str::random(5)))
            ->all();
    }

    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', Str::lower(preg_replace('/[^A-Za-z0-9]/', '', $code)), (string) config('app.key'));
    }

    private function trustSignature(User $user, int $expires): string
    {
        return hash_hmac('sha256', $user->id.'|'.$expires.'|'.$user->two_factor_secret.'|'.$user->password, (string) config('app.key'));
    }
}
