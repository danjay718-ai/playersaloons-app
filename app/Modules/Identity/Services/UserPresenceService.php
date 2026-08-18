<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class UserPresenceService
{
    private const KEY = 'presence:online-users';

    private const TTL_SECONDS = 300;

    public function markOnline(int $userId): void
    {
        try {
            Redis::zadd(self::KEY, [strval($userId) => now()->timestamp]);
        } catch (Throwable $exception) {
            // Presence is intentionally non-critical: Redis degradation must not
            // prevent authenticated traffic from reaching the application.
            Log::warning('Unable to update user presence.', ['exception' => $exception]);
        }
    }

    public function isOnline(int $userId): bool
    {
        try {
            $lastSeen = Redis::zscore(self::KEY, strval($userId));

            return $lastSeen !== null && (int) $lastSeen >= now()->timestamp - self::TTL_SECONDS;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<int> */
    public function onlineUserIds(): array
    {
        try {
            Redis::zremrangebyscore(self::KEY, '-inf', now()->timestamp - self::TTL_SECONDS);

            return array_values(array_map('intval', Redis::zrange(self::KEY, 0, -1)));
        } catch (Throwable) {
            return [];
        }
    }
}
