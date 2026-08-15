<?php
declare(strict_types=1);

use Cake\Cache\Cache;
use Crustum\Audit\Diagnostic\CacheConfigIsReachable;
use Crustum\Audit\Result\Status;
use Crustum\Audit\Test\Fixtures\Cache\UnreachableCacheEngine;

it('passes when the configured cache configs can be reached', function (): void {
    $cachePath = audit_cake_core_path();
    Cache::setConfig('audit_probe', ['className' => 'File', 'path' => $cachePath]);

    try {
        expect((new CacheConfigIsReachable('audit_probe'))->check()->status)->toBe(Status::Pass)
            ->and((new CacheConfigIsReachable())->check()->status)->toBe(Status::Pass);
    } finally {
        Cache::drop('audit_probe');
    }
});

it('skips cache probes when none of the requested configs exist', function (): void {
    $result = (new CacheConfigIsReachable('audit_missing_cache'))->check();

    expect($result->status)->toBe(Status::Skip)
        ->and($result->code)->toBe('cache-config-is-reachable.not-configured');
});

it('fails when a cache configuration cannot be reached', function (): void {
    Cache::setConfig('audit_broken_cache', [
        'className' => UnreachableCacheEngine::class,
        'prefix' => 'audit_broken_',
    ]);

    try {
        $result = (new CacheConfigIsReachable('audit_broken_cache'))->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->code)->toBe('cache-config-is-reachable.unreachable');
    } finally {
        Cache::drop('audit_broken_cache');
    }
});
