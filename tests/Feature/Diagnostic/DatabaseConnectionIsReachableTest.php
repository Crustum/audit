<?php
declare(strict_types=1);

use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;
use Cake\Datasource\ConnectionManager;
use Crustum\Audit\Diagnostic\DatabaseConnectionIsReachable;
use Crustum\Audit\Result\Status;

it('checks the default database connection', function (): void {
    expect(new DatabaseConnectionIsReachable('test')->check()->status)->toBe(Status::Pass)
        ->and(new DatabaseConnectionIsReachable('missing')->check()->status)->toBe(Status::Skip);
});

it('links to database configuration when the connection cannot be reached', function (): void {
    ConnectionManager::setConfig('audit_unreachable', [
        'className' => Connection::class,
        'driver' => Mysql::class,
        'host' => '127.0.0.1',
        'port' => 1,
        'database' => 'audit_unreachable',
        'username' => 'audit',
        'password' => 'audit',
        'flags' => [
            PDO::ATTR_TIMEOUT => 1,
        ],
    ]);

    try {
        $result = new DatabaseConnectionIsReachable('audit_unreachable')->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->code)->toBe('database-connection-is-reachable.unreachable')
            ->and($result->details)->not->toBeNull()
            ->and($result->links)->not->toBe([]);
    } finally {
        ConnectionManager::drop('audit_unreachable');
    }
});
