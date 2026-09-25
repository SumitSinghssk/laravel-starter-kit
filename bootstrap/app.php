<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\BlockIps;
use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Http\Middleware\HandleRedirects;
use App\Http\Middleware\IdleTimeout;
use App\Http\Middleware\LogAdminActivity;
use App\Http\Middleware\MaintenanceMode;
use App\Http\Middleware\NoIndex;
use App\Http\Middleware\ProtectForm;
use App\Http\Middleware\RecordNotFound;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SecureHeaders;
use App\Services\Firewall;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/admin.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => Authenticate::class,
            'log.admin.activity' => LogAdminActivity::class,
            'guest' => RedirectIfAuthenticated::class,
            'two-factor.required' => EnsureTwoFactorEnabled::class,
            'bot.protect' => ProtectForm::class,
            'idle.timeout' => IdleTimeout::class,
        ]);
        $middleware->encryptCookies(except: ['admin_nav']);
        $middleware->prepend(BlockIps::class);
        $middleware->web(append: [
            SecureHeaders::class,
            MaintenanceMode::class,
        ]);
        $middleware->append(NoIndex::class);
        $middleware->append(HandleRedirects::class);
        $middleware->append(RecordNotFound::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            rescue(fn () => app(Firewall::class)->record($request, 'rate_limited'), report: false);

            return null;
        });
    })->create();
