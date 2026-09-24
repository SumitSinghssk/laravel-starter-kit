<?php

namespace App\Models;

use App\Enums\CommonStatusEnum;
use App\Traits\Trackable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Redirect extends Model
{
    use Trackable;

    public const CACHE_KEY = 'redirects_map';

    public const STATUS_CODES = [
        301 => ['label' => '301 · Permanent', 'description' => 'The page moved for good. Search engines pass its ranking to the new URL.'],
        302 => ['label' => '302 · Temporary', 'description' => 'The page is away for now. Search engines keep the old URL.'],
        307 => ['label' => '307 · Temporary (strict)', 'description' => 'Like 302, but forms keep their method (POST stays POST).'],
        308 => ['label' => '308 · Permanent (strict)', 'description' => 'Like 301, but forms keep their method (POST stays POST).'],
    ];

    protected $fillable = [
        'source_path',
        'target_url',
        'status_code',
        'status',
        'note',
    ];

    protected $casts = [
        'status' => CommonStatusEnum::class,
        'status_code' => 'integer',
        'hits' => 'integer',
        'last_hit_at' => 'datetime',
    ];

    public static function normalizePath(?string $value): string
    {
        $value = trim((string) $value);
        $path = parse_url($value, PHP_URL_PATH);

        if (! is_string($path)) {
            $path = strtok($value, '?#') ?: '';
        }

        $path = '/'.trim(rawurldecode($path), '/');

        return mb_strtolower($path);
    }

    public static function internalPath(string $target): ?string
    {
        $host = parse_url($target, PHP_URL_HOST);

        if ($host !== null && $host !== false && strcasecmp($host, (string) parse_url(url('/'), PHP_URL_HOST)) !== 0) {
            return null;
        }

        return self::normalizePath($target);
    }

    public static function loopChain(string $source, string $target, ?int $ignoreId = null): ?array
    {
        $others = static::query()->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->pluck('target_url', 'source_path');

        $chain = [$source];
        $next = self::internalPath($target);

        for ($hop = 0; $next !== null && $hop < 20; $hop++) {
            if (in_array($next, $chain, true)) {
                return [...$chain, $next];
            }

            if (! $others->has($next)) {
                return null;
            }

            $chain[] = $next;
            $next = self::internalPath($others[$next]);
        }

        return null;
    }

    public function isExternal(): bool
    {
        return self::internalPath($this->target_url) === null;
    }

    public static function activeMap(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
            ->where('status', CommonStatusEnum::ACTIVE->value)
            ->get(['id', 'source_path', 'target_url', 'status_code'])
            ->mapWithKeys(fn (self $redirect) => [$redirect->source_path => [$redirect->id, $redirect->target_url, $redirect->status_code]])
            ->all());
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }
}
