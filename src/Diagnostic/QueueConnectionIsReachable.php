<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Cake\Queue\QueueManager;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\Link;
use Crustum\Audit\Result\Message;
use Crustum\Audit\Support\Configured;
use Override;
use Throwable;

/**
 * Probes a configured CakePHP Queue connection.
 */
final class QueueConnectionIsReachable extends Diagnostic
{
    public string $name = 'Queue connects';

    public string $group = 'queue';

    /**
     * Constructor.
     *
     * @param string $config Queue configuration name.
     */
    public function __construct(private readonly string $config = 'default')
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
            'unavailable' => 'cakephp/queue is not installed.',
            'not-configured' => 'Queue configuration [{config}] is not configured.',
            'unreachable' => Message::make(
                'The application cannot reach queue configuration [{config}].',
                'Check Queue configuration and the backing transport URL.',
            )->link(Link::docs('plugins', null, 'Queue documentation')),
            'reachable' => 'The application can reach the queue configuration.',
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        if (!class_exists(QueueManager::class)) {
            return $this->skip('unavailable');
        }

        $config = Configured::string('Audit.queue.config', $this->config) ?? $this->config;
        if (!in_array($config, QueueManager::configured(), true)) {
            return $this->skip('not-configured', ['config' => $config]);
        }

        try {
            QueueManager::engine($config);
        } catch (Throwable $throwable) {
            return $this->fail('unreachable', ['config' => $config])
                ->withDetails($throwable->getMessage());
        }

        return $this->pass('reachable');
    }
}
