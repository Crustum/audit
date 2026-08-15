<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Cake\Queue\QueueManager;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\EnvironmentMode;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\Link;
use Crustum\Audit\Result\Message;
use Crustum\Audit\Support\Configured;
use Override;

/**
 * Warns when production queues run on a synchronous null transport.
 */
final class QueueConnectionIsAsynchronous extends Diagnostic
{
    public string $name = 'Queue runs asynchronously';

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
            'sync-production' => Message::make(
                'Queued jobs run synchronously in production.',
                'Configure Queue to use a background transport such as Redis, database, or a broker URL.',
            )->link(Link::docs('plugins', null, 'Queue documentation')),
            'sync-local' => 'Queued jobs run synchronously.',
            'async' => 'Queued jobs are processed asynchronously.',
            'async-local' => Message::make(
                'Queued jobs are processed asynchronously.',
                'If jobs are not being processed, make sure a queue worker is running via `bin/cake queue worker`.',
            )->link(Link::docs('plugins', null, 'Queue documentation')),
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
        $settings = QueueManager::getConfig($config);
        if ($settings === null) {
            return $this->skip('not-configured', ['config' => $config]);
        }

        $synchronous = $this->isSynchronous($settings['url'] ?? null);
        $production = EnvironmentMode::current()->isProduction();

        if ($synchronous && $production) {
            return $this->warn('sync-production');
        }

        if ($synchronous) {
            return $this->pass('sync-local');
        }

        return $production ? $this->pass('async') : $this->notice('async-local');
    }

    /**
     * Determine whether a queue URL is synchronous.
     *
     * @param mixed $url Queue URL configuration.
     * @return bool
     */
    private function isSynchronous(mixed $url): bool
    {
        if (is_string($url)) {
            return str_starts_with($url, 'null:') || $url === 'null';
        }

        if (!is_array($url)) {
            return false;
        }

        $transport = $url['transport'] ?? null;

        return is_string($transport) && (str_starts_with($transport, 'null:') || $transport === 'null');
    }
}
