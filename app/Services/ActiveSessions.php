<?php

namespace App\Services;

use App\Models\User;
use App\Support\UserAgent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActiveSessions
{
    public const ONLINE_MINUTES = 5;

    public static function key(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }

    public function forUser(User $user, ?string $currentId = null): Collection
    {
        return $this->query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'payload', 'last_activity'])
            ->map(function ($row) use ($currentId) {
                $lastActive = Carbon::createFromTimestamp($row->last_activity);

                return [
                    'key' => self::key($row->id),
                    'is_current' => $currentId !== null && hash_equals($row->id, $currentId),
                    'ip' => $row->ip_address,
                    ...UserAgent::describe($row->user_agent),
                    'last_active' => $lastActive,
                    'signed_in_at' => $this->signedInAt($row->payload),
                    'online' => $lastActive->gt(now()->subMinutes(self::ONLINE_MINUTES)),
                ];
            })
            ->sortByDesc('is_current')
            ->values();
    }

    public function lastActivity(array $userIds): array
    {
        if (! $userIds) {
            return [];
        }

        return $this->query()
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id')
            ->selectRaw('user_id, MAX(last_activity) as last_activity')
            ->pluck('last_activity', 'user_id')
            ->map(fn ($timestamp) => Carbon::createFromTimestamp($timestamp))
            ->all();
    }

    public function end(User $user, string $key, ?string $currentId = null): bool
    {
        $id = $this->query()
            ->where('user_id', $user->getKey())
            ->pluck('id')
            ->first(fn ($id) => hash_equals(self::key($id), $key) && ($currentId === null || ! hash_equals($id, $currentId)));

        if (! $id) {
            return false;
        }

        DB::table(config('session.table'))->where('id', $id)->delete();
        $this->resetRememberToken($user);

        return true;
    }

    public function endAll(User $user, ?string $keepId = null): int
    {
        $count = DB::table(config('session.table'))
            ->where('user_id', $user->getKey())
            ->when($keepId, fn ($q) => $q->where('id', '!=', $keepId))
            ->delete();

        $this->resetRememberToken($user);

        return $count;
    }

    private function query()
    {
        return DB::table(config('session.table'))
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->getTimestamp());
    }

    private function resetRememberToken(User $user): void
    {
        $token = Str::random(60);
        DB::table($user->getTable())->where($user->getKeyName(), $user->getKey())->update(['remember_token' => $token]);
        $user->setRememberToken($token);
    }

    private function signedInAt(string $payload): ?Carbon
    {
        $data = rescue(fn () => unserialize(base64_decode($payload), ['allowed_classes' => false]), null, report: false);
        $timestamp = is_array($data) ? ($data['auth_signed_in_at'] ?? null) : null;

        return is_int($timestamp) ? Carbon::createFromTimestamp($timestamp) : null;
    }
}
