<?php
declare(strict_types=1);

use Cake\Datasource\ConnectionManager;
use Crustum\Audit\Contract\MigrationsClientInterface;
use Crustum\Audit\Diagnostic\MigrationsAreUpToDate;
use Crustum\Audit\EnvironmentMode;
use Crustum\Audit\Result\Status;
use Crustum\Audit\Support\MigrationsClient;
use Migrations\Migrations;

beforeEach(function (): void {
    audit_reset_environment_mode();
});

it('fails when TestApp migrations are pending and passes after they are applied', function (): void {
    [$connection, $path] = audit_migrations_connection();

    try {
        $client = new MigrationsClient(new Migrations([
            'connection' => $connection,
            'source' => 'Migrations',
        ]));
        $diagnostic = new MigrationsAreUpToDate($client, $connection);

        $pending = $diagnostic->check();
        expect($pending->status)->toBe(Status::Fail)
            ->and($pending->code)->toBe('migrations-are-up-to-date.pending')
            ->and($pending->fixableEnvironments)->toBe([EnvironmentMode::Local])
            ->and($pending->details)->toContain('CreateAuditProbeItems');

        expect($diagnostic->fix($pending)->status)->toBe(Status::Pass)
            ->and($diagnostic->check()->status)->toBe(Status::Pass)
            ->and($diagnostic->check()->code)->toBe('migrations-are-up-to-date.current');
    } finally {
        if (ConnectionManager::getConfig($connection) !== null) {
            ConnectionManager::get($connection)->getDriver()->disconnect();
            ConnectionManager::drop($connection);
        }

        if (is_file($path)) {
            unlink($path);
        }
    }
});

it('reports when pending migrations cannot be applied', function (): void {
    $migrations = new class implements MigrationsClientInterface {
        public function status(array $options = []): array
        {
            return [['status' => 'down', 'name' => 'CreateBroken']];
        }

        public function migrate(array $options = []): bool
        {
            throw new RuntimeException('Migration apply failed.');
        }
    };
    $diagnostic = new MigrationsAreUpToDate($migrations);
    $result = $diagnostic->check();

    expect($result->status)->toBe(Status::Fail)
        ->and($diagnostic->fix($result)->status)->toBe(Status::Fail)
        ->and($diagnostic->fix($result)->details)->toContain('Migration apply failed.');
});

it('passes when there are no migration files', function (): void {
    $migrations = new class implements MigrationsClientInterface {
        public function status(array $options = []): array
        {
            return [];
        }

        public function migrate(array $options = []): bool
        {
            return true;
        }
    };

    expect(new MigrationsAreUpToDate($migrations)->check()->status)->toBe(Status::Pass)
        ->and(new MigrationsAreUpToDate($migrations)->check()->code)
        ->toBe('migrations-are-up-to-date.no-files');
});

it('reports migration inspection failures', function (): void {
    $migrations = new class implements MigrationsClientInterface {
        public function status(array $options = []): array
        {
            throw new RuntimeException('The phinxlog table does not exist.');
        }

        public function migrate(array $options = []): bool
        {
            return false;
        }
    };

    expect(new MigrationsAreUpToDate($migrations)->check()->status)->toBe(Status::Fail)
        ->and(new MigrationsAreUpToDate($migrations)->check()->code)
        ->toBe('migrations-are-up-to-date.repository-missing');
});
