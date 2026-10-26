<?php
declare(strict_types=1);

use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Crustum\Audit\Diagnostic\SqliteDatabaseExists;
use Crustum\Audit\Result\Status;

it('creates a missing SQLite database file', function (): void {
    $path = audit_cake_core_path();
    ConnectionManager::setConfig('audit_sqlite', [
        'className' => Connection::class,
        'driver' => Sqlite::class,
        'database' => 'database' . DS . 'audit.sqlite',
    ]);

    try {
        $diagnostic = new SqliteDatabaseExists('audit_sqlite', $path);
        $result = $diagnostic->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($diagnostic->fix($result)->status)->toBe(Status::Pass)
            ->and(is_file($path . DS . 'database' . DS . 'audit.sqlite'))->toBeTrue();
    } finally {
        ConnectionManager::drop('audit_sqlite');
    }
});

it('preserves an absolute Windows sqlite database path', function (string $database): void {
    ConnectionManager::setConfig('audit_sqlite_windows', [
        'className' => Connection::class,
        'driver' => Sqlite::class,
        'database' => $database,
    ]);

    try {
        $result = new SqliteDatabaseExists('audit_sqlite_windows')->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->context['database'])
            ->toBe(str_replace(['/', '\\'], DS, $database));
    } finally {
        ConnectionManager::drop('audit_sqlite_windows');
    }
})->with([
    'backslash separators' => 'C:' . DS . 'Users' . DS . 'MyUser' . DS . 'Herd' . DS . 'my-project' . DS . 'database' . DS . 'database.sqlite',
    'forward slash separators' => 'C:/Users/MyUser/Herd/my-project/database/database.sqlite',
]);

it('passes when the SQLite database file already exists', function (): void {
    $path = audit_cake_core_path();
    $database = $path . DS . 'existing.sqlite';
    file_put_contents($database, '');
    ConnectionManager::setConfig('audit_sqlite_exists', [
        'className' => Connection::class,
        'driver' => Sqlite::class,
        'database' => $database,
    ]);

    try {
        expect(new SqliteDatabaseExists('audit_sqlite_exists', $path)->check()->status)->toBe(Status::Pass);
    } finally {
        ConnectionManager::drop('audit_sqlite_exists');
    }
});

it('skips SQLite checks for non-sqlite and memory databases', function (): void {
    ConnectionManager::setConfig('audit_sqlite_memory', [
        'className' => Connection::class,
        'driver' => Sqlite::class,
        'database' => ':memory:',
    ]);
    ConnectionManager::setConfig('audit_not_sqlite', [
        'className' => Connection::class,
        'driver' => Mysql::class,
        'database' => 'audit',
        'host' => '127.0.0.1',
        'username' => 'audit',
        'password' => 'audit',
    ]);

    try {
        expect(new SqliteDatabaseExists('audit_sqlite_memory')->check()->status)->toBe(Status::Skip)
            ->and(new SqliteDatabaseExists('audit_sqlite_memory')->check()->code)
            ->toBe('sqlite-database-exists.not-file')
            ->and(new SqliteDatabaseExists('audit_not_sqlite')->check()->status)->toBe(Status::Skip)
            ->and(new SqliteDatabaseExists('audit_not_sqlite')->check()->code)
            ->toBe('sqlite-database-exists.not-sqlite');
    } finally {
        ConnectionManager::drop('audit_sqlite_memory');
        ConnectionManager::drop('audit_not_sqlite');
    }
});
