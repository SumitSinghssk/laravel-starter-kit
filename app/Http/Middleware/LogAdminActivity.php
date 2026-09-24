<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LogAdminActivity
{
    private array $skipRoutes = [
        'admin.login',
        'admin.activity-logs.index',
        'admin.activity-logs.show',
    ];

    private array $skipPatterns = [
        'api/*',
        '*/export*',
        '*/download*',
        '*/image*',
        '*/media*',
        'admin/activity-logs*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET')) {
            return $response;
        }

        if (! Auth::check()) {
            return $response;
        }

        if (! in_array($response->getStatusCode(), [200, 301, 302])) {
            return $response;
        }

        $routeName = $request->route()?->getName();

        if ($routeName && in_array($routeName, $this->skipRoutes)) {
            return $response;
        }

        foreach ($this->skipPatterns as $pattern) {
            if ($request->is($pattern)) {
                return $response;
            }
        }

        $pageTitle = $this->resolvePageTitle($request, $routeName);

        ActivityLogger::viewed($pageTitle);

        return $response;
    }

    private function resolvePageTitle(Request $request, ?string $routeName): string
    {
        if ($routeName) {
            return $this->formatRouteName($request, $routeName);
        }

        return $this->buildTitleFromUrl($request);
    }

    private function buildTitleFromUrl(Request $request): string
    {
        $segments = collect($request->segments())
            ->reject(fn ($s) => $s === 'admin')
            ->values();

        if ($segments->isEmpty()) {
            return 'Dashboard';
        }

        $parts = [];
        $lastId = null;

        foreach ($segments as $segment) {
            if (is_numeric($segment)) {
                $lastId = $segment;
            } else {
                $parts[] = ucwords(str_replace(['-', '_'], ' ', $segment));
            }
        }

        $title = implode(' > ', $parts);

        if ($lastId) {
            $title .= " #{$lastId}";
        }

        return $title ?: 'Admin Panel';
    }

    private function formatRouteName(Request $request, string $routeName): string
    {
        $parts = explode('.', $routeName);

        if ($parts[0] === 'admin') {
            array_shift($parts);
        }

        $formatted = collect($parts)->map(function ($part) {
            return match ($part) {
                'index' => 'List',
                'create' => 'Create',
                'edit' => 'Edit',
                'show' => 'View',
                default => ucwords(str_replace(['-', '_'], ' ', $part)),
            };
        });

        $title = $formatted->implode(' > ');

        $id = collect($request->route()->parameters())->first();

        if ($id) {
            $id = is_object($id) ? $id->getKey() : $id;
            $title .= " #{$id}";
        }

        return $title;
    }
}
