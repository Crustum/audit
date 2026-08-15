<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\Link;
use Crustum\Audit\Result\Message;
use Override;
use RuntimeException;
use Throwable;

/**
 * Probes a configured CakePHP database connection.
 */
final class DatabaseConnectionIsReachable extends Diagnostic
{
    public string $name = 'Database connects';

    public string $group = 'database';

    /**
     * Constructor.
     *
     * @param string $connection Connection name.
     */
    public function __construct(private readonly string $connection = 'default')
    {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function messages(): array
    {
        return [
            'connection-missing' => 'Database connection [{connection}] is not configured.',
            'unreachable' => Message::make(
                'The application cannot connect to database connection [{connection}].',
                'Check the CakePHP datasource and database credentials.',
            )->link(Link::docs('orm/database-basics', 'configuration')),
            'reachable' => 'The application can connect to the database connection.',
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        if (!in_array($this->connection, ConnectionManager::configured(), true)) {
            return $this->skip('connection-missing', ['connection' => $this->connection]);
        }

        try {
            $connection = ConnectionManager::get($this->connection);
            if (!$connection instanceof Connection) {
                throw new RuntimeException(sprintf(
                    'Database connection [%s] does not support SQL probes.',
                    $this->connection,
                ));
            }

            $statement = $connection->execute('SELECT 1');
            $statement->closeCursor();
        } catch (Throwable $throwable) {
            return $this->fail('unreachable', ['connection' => $this->connection])
                ->withDetails($throwable->getMessage());
        }

        return $this->pass('reachable');
    }
}
