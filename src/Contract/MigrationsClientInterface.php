<?php
declare(strict_types=1);

namespace Crustum\Audit\Contract;

/**
 * Narrow migrations inspection/apply API used by Audit diagnostics.
 */
interface MigrationsClientInterface
{
    /**
     * Get migration statuses.
     *
     * @param array<string, mixed> $options Backend options.
     * @return array<int|string, mixed>
     */
    public function status(array $options = []): array;

    /**
     * Apply pending migrations.
     *
     * @param array<string, mixed> $options Backend options.
     * @return bool
     */
    public function migrate(array $options = []): bool;
}
