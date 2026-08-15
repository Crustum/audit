<?php
declare(strict_types=1);

namespace Crustum\Audit\Support;

use Crustum\Audit\Contract\MigrationsClientInterface;
use Migrations\Migrations;

/**
 * Adapter around cakephp/migrations' programmatic API.
 */
final readonly class MigrationsClient implements MigrationsClientInterface
{
    /**
     * Constructor.
     *
     * @param \Migrations\Migrations $migrations Migrations API.
     */
    public function __construct(private Migrations $migrations)
    {
    }

    /**
     * @inheritDoc
     */
    public function status(array $options = []): array
    {
        return $this->migrations->status($options);
    }

    /**
     * @inheritDoc
     */
    public function migrate(array $options = []): bool
    {
        return $this->migrations->migrate($options);
    }
}
