<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class MetaCacheService
{
    public const RBAC_VERSION_KEY = 'meta:rbac:version';

    public const USER_BOOTSTRAP_VERSION_KEY_PREFIX = 'meta:bootstrap:user_version:';

    public function rbacVersion(): int
    {
        return (int) Cache::get(self::RBAC_VERSION_KEY, 1);
    }

    public function bumpRbacVersion(): int
    {
        Cache::add(self::RBAC_VERSION_KEY, 1, now()->addDays(7));

        return (int) Cache::increment(self::RBAC_VERSION_KEY);
    }

    public function userBootstrapVersion(int $userId): int
    {
        return (int) Cache::get($this->userVersionKey($userId), 1);
    }

    public function bumpUserBootstrapVersion(int $userId): int
    {
        $key = $this->userVersionKey($userId);
        Cache::add($key, 1, now()->addDays(7));

        return (int) Cache::increment($key);
    }

    protected function userVersionKey(int $userId): string
    {
        return self::USER_BOOTSTRAP_VERSION_KEY_PREFIX . $userId;
    }
}
