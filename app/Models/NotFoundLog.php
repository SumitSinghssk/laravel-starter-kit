<?php

namespace App\Models;

use App\Services\Media\MediaLibrary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotFoundLog extends Model
{
    protected $fillable = [
        'path',
        'hits',
        'last_referrer',
        'last_user_agent',
        'ignored',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'hits' => 'integer',
        'ignored' => 'boolean',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public static function record(Request $request): void
    {
        $path = Str::limit(Redirect::normalizePath($request->getPathInfo()), 500, '');
        $now = now();
        $details = [
            'last_referrer' => self::referrer($request),
            'last_user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'last_seen_at' => $now,
            'updated_at' => $now,
        ];

        $updated = DB::table('not_found_logs')->where('path', $path)->update([...$details, 'hits' => DB::raw('hits + 1')]);

        if ($updated === 0) {
            $inserted = DB::table('not_found_logs')->insertOrIgnore([...$details, 'path' => $path, 'hits' => 1, 'first_seen_at' => $now, 'created_at' => $now]);

            if ($inserted === 0) {
                DB::table('not_found_logs')->where('path', $path)->update([...$details, 'hits' => DB::raw('hits + 1')]);
            }
        }
    }

    private static function referrer(Request $request): ?string
    {
        $referrer = (string) $request->headers->get('referer');

        if ($referrer === '' || ! preg_match('#^https?://#i', $referrer) || rtrim($referrer, '/') === rtrim($request->fullUrl(), '/')) {
            return null;
        }

        return Str::limit($referrer, 1000, '');
    }

    public function getReferrerLabelAttribute(): ?string
    {
        if (! $this->last_referrer) {
            return null;
        }

        $host = parse_url($this->last_referrer, PHP_URL_HOST);

        if ($host && strcasecmp($host, (string) parse_url(url('/'), PHP_URL_HOST)) === 0) {
            $located = app(MediaLibrary::class)->locate($this->last_referrer);

            return '/'.($located ? ($located[0] === MediaFile::STORAGE ? 'storage/' : '').$located[1] : '');
        }

        return $host ? preg_replace('/^www\./', '', $host) : $this->last_referrer;
    }

    public function isInternalReferrer(): bool
    {
        $host = parse_url((string) $this->last_referrer, PHP_URL_HOST);

        return $host && strcasecmp($host, (string) parse_url(url('/'), PHP_URL_HOST)) === 0;
    }
}
