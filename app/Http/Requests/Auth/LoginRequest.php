<?php

namespace App\Http\Requests\Auth;

use App\Enums\CommonStatusEnum;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(?string $guard = null): void
    {
        $this->ensureIsNotRateLimited();

        $guard = $guard ?: Auth::getDefaultDriver();

        $model = $guard === 'customer' ? Customer::class : User::class;

        $user = $model::withTrashed()->where('email', $this->input('email'))->first();

        if ($guard === 'customer') {
            if ($user && $user->trashed()) {
                throw ValidationException::withMessages([
                    'email' => 'Your account has been deleted. Please contact support.',
                ]);
            }

            if (! $user) {
                try {
                    $token = str()->random(60);
                    $user = $model::create([
                        'name' => $this->input('email'),
                        'email' => $this->input('email'),
                        'password' => $this->input('password'),
                        'status' => CommonStatusEnum::ACTIVE->value,
                        'is_verified' => false,
                        'verification_token' => $token,
                    ]);

                    $url = route('customer.verify', $token);

                    session()->flash('info', 'A verification link has been sent to your email. Please verify your account before logging in.');

                    throw ValidationException::withMessages([
                        'message' => '',
                    ]);
                } catch (UniqueConstraintViolationException $e) {
                    $user = $model::where('email', $this->input('email'))->first();
                }
            }

            if ($user && ! $user->is_verified) {
                $token = str()->random(60);
                $user->update(['verification_token' => $token]);

                $url = route('customer.verify', $token);

                session()->flash('warning', 'Please verify your email. A new verification link has been sent.');

                throw ValidationException::withMessages([
                    'message' => '',
                ]);
            }
        }
        if (! Auth::guard($guard)->attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if ($user && ($user->trashed() || $user->status?->value !== CommonStatusEnum::ACTIVE->value)) {
            Auth::guard($guard)->logout();

            throw ValidationException::withMessages([
                'email' => $user->trashed()
                    ? 'Your account has been deleted. Please contact support.'
                    : 'Your account is not active. Please contact support.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->input('email')).'|'.$this->ip());
    }
}
