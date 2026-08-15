<?php
declare(strict_types=1);

namespace Crustum\Audit\Test\Fixtures\Cache;

use Cake\Cache\CacheEngine;
use DateInterval;
use Override;

/**
 * Cache engine that never stores values (for unreachable-config probes).
 */
final class UnreachableCacheEngine extends CacheEngine
{
    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function increment(string $key, int $offset = 1): int|false
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function decrement(string $key, int $offset = 1): int|false
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function clearGroup(string $group): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function add(string $key, mixed $value): bool
    {
        return false;
    }
}
