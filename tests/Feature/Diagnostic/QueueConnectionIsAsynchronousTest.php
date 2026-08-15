<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Queue\QueueManager;
use Crustum\Audit\Diagnostic\QueueConnectionIsAsynchronous;
use Crustum\Audit\Result\Status;

beforeEach(function (): void {
    audit_reset_environment_mode();

    foreach (QueueManager::configured() as $name) {
        QueueManager::drop($name);
    }
});

it('warns about synchronous production queues and notices async local queues', function (): void {
    QueueManager::setConfig('default', ['url' => 'null:']);
    Configure::write('Audit.environment', 'production');
    expect((new QueueConnectionIsAsynchronous())->check()->status)->toBe(Status::Warn);

    QueueManager::drop('default');
    QueueManager::setConfig('default', ['url' => 'file://' . str_replace('\\', '/', TMP) . 'audit-queue']);
    Configure::write('Audit.environment', 'local');
    expect((new QueueConnectionIsAsynchronous())->check()->status)->toBe(Status::Notice);
});

it('passes when queues run synchronously in local environments', function (): void {
    QueueManager::setConfig('default', ['url' => 'null:']);
    Configure::write('Audit.environment', 'local');

    expect((new QueueConnectionIsAsynchronous())->check()->status)->toBe(Status::Pass)
        ->and((new QueueConnectionIsAsynchronous())->check()->code)
        ->toBe('queue-connection-is-asynchronous.sync-local');
});

it('passes when queued jobs are asynchronous outside local environments', function (): void {
    QueueManager::setConfig('default', ['url' => 'file://' . str_replace('\\', '/', TMP) . 'audit-queue-prod']);
    Configure::write('Audit.environment', 'production');

    expect((new QueueConnectionIsAsynchronous())->check()->status)->toBe(Status::Pass)
        ->and((new QueueConnectionIsAsynchronous())->check()->code)
        ->toBe('queue-connection-is-asynchronous.async');
});

it('warns when queues run synchronously in unmapped environments', function (): void {
    QueueManager::setConfig('default', ['url' => 'null:']);
    Configure::write('Audit.environment', 'staging');

    expect((new QueueConnectionIsAsynchronous())->check()->status)->toBe(Status::Warn);
});

it('skips asynchronous checks when the default queue connection is not configured', function (): void {
    expect((new QueueConnectionIsAsynchronous())->check()->status)->toBe(Status::Skip)
        ->and((new QueueConnectionIsAsynchronous())->check()->code)
        ->toBe('queue-connection-is-asynchronous.not-configured');
});
