<?php
declare(strict_types=1);

use Cake\Queue\QueueManager;
use Crustum\Audit\Diagnostic\QueueConnectionIsReachable;
use Crustum\Audit\Result\Status;

beforeEach(function (): void {
    audit_reset_environment_mode();

    foreach (QueueManager::configured() as $name) {
        QueueManager::drop($name);
    }
});

it('probes configured queue transports', function (): void {
    QueueManager::setConfig('default', ['url' => 'null:']);

    expect(new QueueConnectionIsReachable()->check()->status)->toBe(Status::Pass);
});

it('skips queue reachability when the connection is not configured', function (): void {
    expect(new QueueConnectionIsReachable('missing-queue')->check()->status)->toBe(Status::Skip);
});
