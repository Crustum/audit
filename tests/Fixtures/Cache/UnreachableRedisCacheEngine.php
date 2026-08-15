<?php
declare(strict_types=1);

namespace Crustum\Audit\Test\Fixtures\Cache;

use Cake\Cache\CacheEngine;
use DateInterval;
use Override;
use RuntimeException;

/**
 * Redis-named cache engine that always fails (for active Redis probe failures).
 */
final class UnreachableRedisCacheEngine extends CacheEngine
{
    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        throw new RuntimeException('Unable to connect to Redis at 127.0.0.1:1.');
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
