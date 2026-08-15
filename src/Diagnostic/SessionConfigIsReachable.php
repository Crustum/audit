<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Cake\Cache\Cache;
use Cake\Core\Configure;
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
 * Verifies the configured CakePHP session backend can be resolved.
 */
final class SessionConfigIsReachable extends Diagnostic
{
    public string $name = 'Session connects';

    public string $group = 'session';

    /**
     * @inheritDoc
     */
    #[Override]
    protected function messages(): array
    {
        return [
            'not-configured' => 'The application does not have a session configuration.',
            'unreachable' => Message::make(
                'The application cannot reach the configured session backend [{handler}].',
                'Check the CakePHP Session defaults and handler configuration.',
            )->link(Link::docs('development/sessions', 'session-configuration')),
            'reachable' => 'The application can reach the configured session backend.',
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        $session = Configure::read('Session');
        if (!is_array($session)) {
            return $this->skip('not-configured');
        }

        $handler = $session['handler'] ?? ($session['defaults'] ?? 'php');
        $label = is_array($handler) ? (string)($handler['className'] ?? 'custom') : (string)$handler;

        try {
            $this->probe($handler);
        } catch (Throwable $throwable) {
            return $this->fail('unreachable', ['handler' => $label])
                ->withDetails($throwable->getMessage());
        }

        return $this->pass('reachable');
    }

    /**
     * Probe a session handler definition.
     *
     * @param mixed $handler Session handler.
     * @return void
     */
    private function probe(mixed $handler): void
    {
        if (!is_array($handler)) {
            return;
        }

        $className = $handler['className'] ?? null;
        if ($className === 'Cache' || $className === 'CacheSession') {
            $config = is_string($handler['config'] ?? null) ? $handler['config'] : 'default';
            if (!in_array($config, Cache::configured(), true)) {
                throw new RuntimeException(sprintf('Cache configuration [%s] does not exist.', $config));
            }
        }

        if ($className === 'Database' || $className === 'DatabaseSession') {
            $connection = is_string($handler['connection'] ?? null) ? $handler['connection'] : 'default';
            if (!in_array($connection, ConnectionManager::configured(), true)) {
                throw new RuntimeException(sprintf('Database connection [%s] does not exist.', $connection));
            }

            $resolved = ConnectionManager::get($connection);
            if (!$resolved instanceof Connection) {
                throw new RuntimeException(sprintf(
                    'Database connection [%s] does not support SQL probes.',
                    $connection,
                ));
            }

            $statement = $resolved->execute('SELECT 1');
            $statement->closeCursor();
        }
    }
}
