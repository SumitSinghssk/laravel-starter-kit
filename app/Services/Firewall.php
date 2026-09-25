<?php

namespace App\Services;

use App\Models\BlockedRequest;
use App\Models\IpBlock;
use App\Models\User;
use App\Support\SecuritySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

class Firewall
{
    private const CACHE_KEY = 'firewall:active-blocks';

    public function blockFor(?string $ip): ?IpBlock
    {
        if (! $ip) {
            return null;
        }

        $rules = Cache::remember(self::CACHE_KEY, 60, fn () => IpBlock::active()->get(['id', 'ip', 'expires_at'])->map(fn ($block) => [$block->id, $block->ip, $block->expires_at?->timestamp])->all());

        foreach ($rules as [$id, $rule, $expires]) {
            if (($expires === null || $expires > now()->timestamp) && self::matches($ip, $rule)) {
                return IpBlock::find($id);
            }
        }

        return null;
    }

    public static function matches(string $ip, string $rule): bool
    {
        try {
            return $ip === $rule || IpUtils::checkIp($ip, $rule);
        } catch (Throwable) {
            return false;
        }
    }

    public static function validRule(string $rule): bool
    {
        [$address, $mask] = array_pad(explode('/', trim($rule), 2), 2, null);

        if (! filter_var($address, FILTER_VALIDATE_IP)) {
            return false;
        }

        if ($mask === null) {
            return true;
        }

        $max = str_contains($address, ':') ? 128 : 32;

        return ctype_digit($mask) && (int) $mask >= 1 && (int) $mask <= $max;
    }

    public function block(string $ip, ?string $reason, ?int $minutes, ?User $by = null, bool $automatic = false): IpBlock
    {
        $block = IpBlock::create([
            'ip' => trim($ip),
            'reason' => $reason,
            'automatic' => $automatic,
            'expires_at' => $minutes ? now()->addMinutes($minutes) : null,
            'created_by' => $by?->id,
        ]);

        $this->flush();

        return $block;
    }

    public function unblock(IpBlock $block): void
    {
        $block->delete();
        $this->flush();
    }

    public function unblockIp(string $ip): int
    {
        $count = IpBlock::where('ip', trim($ip))->delete();
        $this->flush();

        return $count;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function record(Request $request, string $reason): void
    {
        $ip = $request->ip();

        rescue(fn () => BlockedRequest::create([
            'ip' => $ip,
            'reason' => $reason,
            'method' => $request->method(),
            'path' => Str::limit('/'.ltrim($request->path(), '/'), 490, ''),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
            'created_at' => now(),
        ]), report: false);

        if ($reason !== 'ip_blocked') {
            rescue(fn () => $this->maybeAutoBlock($ip), report: false);
        }
    }

    public function purge(): int
    {
        $days = max(1, (int) SecuritySettings::get('log_days'));
        $removed = BlockedRequest::where('created_at', '<', now()->subDays($days))->delete();
        IpBlock::whereNotNull('expires_at')->where('expires_at', '<', now()->subDay())->delete();
        $this->flush();

        return $removed;
    }

    private function maybeAutoBlock(?string $ip): void
    {
        $settings = SecuritySettings::all();

        if (! $ip || ! $settings['auto_block'] || $this->blockFor($ip)) {
            return;
        }

        $recent = BlockedRequest::where('ip', $ip)
            ->where('reason', '!=', 'ip_blocked')
            ->where('created_at', '>=', now()->subMinutes(max(1, (int) $settings['auto_block_window'])))
            ->count();

        if ($recent < max(1, (int) $settings['auto_block_after'])) {
            return;
        }

        $minutes = max(1, (int) $settings['auto_block_minutes']);
        $this->block($ip, "{$recent} blocked requests in {$settings['auto_block_window']} minutes", $minutes, null, true);

        notify('Security', 'IP blocked automatically', "{$ip} was blocked for {$minutes} minutes after {$recent} blocked requests", ['ip' => $ip], route('admin.settings.index', ['tab' => 'security']));
    }
}
