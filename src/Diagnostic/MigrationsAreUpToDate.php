<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Crustum\Audit\Contract\FixableInterface;
use Crustum\Audit\Contract\MigrationsClientInterface;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\EnvironmentMode;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\FixResult;
use Crustum\Audit\Result\Link;
use Crustum\Audit\Result\Message;
use Crustum\Audit\Support\Details;
use Crustum\Audit\Support\MigrationsClient;
use Migrations\Migrations;
use Override;
use Throwable;

/**
 * Reports pending CakePHP migrations.
 */
final class MigrationsAreUpToDate extends Diagnostic implements FixableInterface
{
    public string $name = 'Migrations are current';

    public string $group = 'database';

    /**
     * Constructor.
     *
     * @param \Crustum\Audit\Contract\MigrationsClientInterface|null $migrations Migrations client.
     * @param string $connection Connection name.
     */
    public function __construct(
        private readonly ?MigrationsClientInterface $migrations = null,
        private readonly string $connection = 'default',
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function messages(): array
    {
        $link = Link::docs('migrations', 'running-migrations');

        return [
            'unavailable' => 'cakephp/migrations is not installed.',
            'no-files' => 'The application does not have migration files.',
            'repository-missing' => Message::make(
                'The migrations table does not exist.',
                'Run `bin/cake migrations migrate` to create the migrations table and apply migrations.',
                'Create the migrations table and apply pending migrations?',
            )->link($link),
            'inspection-failed' => 'The application could not inspect database migrations.',
            'current' => 'Database migrations are current.',
            'pending' => Message::make(
                'Database migrations are pending.',
                'Run `bin/cake migrations migrate` to apply the pending migrations.',
                'Apply pending migrations using `bin/cake migrations migrate`?',
            )->link($link),
            'migration-failed' => 'Database migrations could not be applied.',
            'migrated' => 'Database migrations were applied.',
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        $migrations = $this->migrationsApi();
        if (!$migrations instanceof MigrationsClientInterface) {
            return $this->skip('unavailable');
        }

        try {
            $status = $migrations->status(['connection' => $this->connection]);
        } catch (Throwable $throwable) {
            $message = $throwable->getMessage();
            if (str_contains(strtolower($message), 'phinxlog') || str_contains(strtolower($message), 'does not exist')) {
                return $this->fail('repository-missing')->fixable(EnvironmentMode::Local)
                    ->withDetails($message);
            }

            return $this->fail('inspection-failed')->withDetails($message);
        }

        if ($status === []) {
            return $this->pass('no-files');
        }

        $pending = [];
        foreach ($status as $migration) {
            if (!is_array($migration)) {
                continue;
            }

            if (($migration['status'] ?? null) === 'down') {
                $pending[] = (string)($migration['name'] ?? $migration['id'] ?? 'unknown');
            }
        }

        return $pending === []
            ? $this->pass('current')
            : $this->fail('pending')->fixable(EnvironmentMode::Local)->withDetails(Details::bullets($pending));
    }

    /**
     * @inheritDoc
     */
    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        $migrations = $this->migrationsApi();
        if (!$migrations instanceof MigrationsClientInterface) {
            return $this->fixFailed('migration-failed')->withDetails('cakephp/migrations is not installed.');
        }

        try {
            $success = $migrations->migrate(['connection' => $this->connection]);
        } catch (Throwable $throwable) {
            return $this->fixFailed('migration-failed')->withDetails($throwable->getMessage());
        }

        return $success
            ? $this->fixed('migrated')
            : $this->fixFailed('migration-failed');
    }

    /**
     * Resolve the migrations client.
     *
     * @return \Crustum\Audit\Contract\MigrationsClientInterface|null
     */
    private function migrationsApi(): ?MigrationsClientInterface
    {
        if ($this->migrations instanceof MigrationsClientInterface) {
            return $this->migrations;
        }

        return class_exists(Migrations::class)
            ? new MigrationsClient(new Migrations(['connection' => $this->connection]))
            : null;
    }
}
