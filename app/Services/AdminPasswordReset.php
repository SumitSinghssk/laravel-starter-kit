<?php

namespace App\Services;

use App\Enums\CommonStatusEnum;
use App\Mail\AdminPasswordResetMail;
use App\Mail\PasswordChangedMail;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

class AdminPasswordReset
{
    public function __construct(private ActiveSessions $sessions) {}

    public function broker(): PasswordBroker
    {
        return Password::broker('users');
    }

    public function sendLink(string $email, ?string $ip, ?string $userAgent): string
    {
        return $this->broker()->sendResetLink(
            ['email' => $email, 'status' => CommonStatusEnum::ACTIVE->value],
            function (User $user, string $token) use ($ip, $userAgent) {
                try {
                    Mail::to($user)->send(new AdminPasswordResetMail($user, $token, $ip, $userAgent));
                } catch (Throwable $e) {
                    report($e);
                    $this->broker()->deleteToken($user);

                    return 'passwords.send_failed';
                }

                $this->log($user, 'password_reset_requested', "Password reset link sent to {$user->email}", $ip, $userAgent);

                return Password::RESET_LINK_SENT;
            }
        );
    }

    public function tokenIsValid(string $email, string $token): bool
    {
        $user = $this->broker()->getUser(['email' => $email, 'status' => CommonStatusEnum::ACTIVE->value]);

        return $user && $this->broker()->tokenExists($user, $token);
    }

    public function reset(array $credentials, ?string $ip, ?string $userAgent): string
    {
        return $this->broker()->reset(
            [...$credentials, 'status' => CommonStatusEnum::ACTIVE->value],
            fn (User $user, string $password) => $this->changePassword($user, $password, $ip, $userAgent)
        );
    }

    public function changePassword(User $user, string $password, ?string $ip, ?string $userAgent, string $via = 'reset link'): int
    {
        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();

        $ended = $this->sessions->endAll($user);

        event(new PasswordReset($user));

        $this->log($user, 'password_reset', "Password reset with a {$via}; signed out of {$ended} ".Str::plural('session', $ended), $ip, $userAgent);

        notify('Security', 'Password reset', "{$user->name} reset their password with a {$via}", ['ip' => $ip, 'user_agent' => $userAgent], route('admin.users.edit', $user));

        rescue(fn () => Mail::to($user)->send(new PasswordChangedMail($user, $ip, $userAgent)));

        return $ended;
    }

    public static function canDeliver(): bool
    {
        return MailSettings::canDeliver();
    }

    private function log(User $user, string $action, string $description, ?string $ip, ?string $userAgent): void
    {
        rescue(fn () => ActivityLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'model_type' => User::class,
            'model_id' => $user->id,
            'model_name' => $user->name,
            'email' => $user->email,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'description' => $description,
        ]), report: false);
    }
}
