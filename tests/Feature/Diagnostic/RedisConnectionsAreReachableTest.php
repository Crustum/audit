<?php
declare(strict_types=1);

use Cake\Cache\Cache;
use Crustum\Audit\Diagnostic\RedisConnectionsAreReachable;
use Crustum\Audit\Result\Status;
use Crustum\Audit\Test\Fixtures\Cache\UnreachableRedisCacheEngine;

it('skips redis probes when no redis backends are active', function (): void {
    expect((new RedisConnectionsAreReachable())->check()->status)->toBe(Status::Skip);
});

it('fails when an active Redis cache backend cannot be reached', function (): void {
    Cache::setConfig('audit_redis_unreachable', [
        'className' => UnreachableRedisCacheEngine::class,
        'prefix' => 'audit_redis_',
    ]);

    try {
        $result = (new RedisConnectionsAreReachable())->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->code)->toBe('redis-connections-are-reachable.unreachable')
            ->and($result->details)->toContain('Unable to connect to Redis');
    } finally {
        Cache::drop('audit_redis_unreachable');
    }
});
