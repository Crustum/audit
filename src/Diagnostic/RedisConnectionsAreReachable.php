<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Queue\QueueManager;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\Link;
use Crustum\Audit\Result\Message;
use Crustum\Audit\Support\Details;
use Override;
use Redis;
use RuntimeException;
use Throwable;

/**
 * Probes Redis backends used by active CakePHP services.
 */
final class RedisConnectionsAreReachable extends Diagnostic
{
    public string $name = 'Redis connects';

    public string $group = 'cache';

    /**
     * @inheritDoc
     */
    #[Override]
    protected function messages(): array
    {
        return [
            'not-configured' => 'The application is not using Redis-backed cache, queue, or session storage.',
            'unreachable' => Message::make(
                'The application cannot reach every active Redis backend.',
                'Check Redis host, port, credentials, and CakePHP cache/queue configuration.',
            )->link(Link::docs('core-libraries/caching', 'redis-engine')),
            'reachable' => 'The application can reach every active Redis backend.',
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        $targets = $this->targets();
        if ($targets === []) {
            return $this->skip('not-configured');
        }

        $failures = [];
        foreach ($targets as $label => $probe) {
            try {
                $probe();
            } catch (Throwable $throwable) {
                $failures[$label] = $throwable->getMessage();
            }
        }

        return $failures === []
            ? $this->pass('reachable')
            : $this->fail('unreachable')->withDetails(Details::failures($failures));
    }

    /**
     * Get Redis probe callables keyed by label.
     *
     * @return array<string, callable(): void>
     */
    private function targets(): array
    {
        $targets = [];
        foreach (Cache::configured() as $name) {
            $config = Cache::getConfig($name);
            if (!is_array($config)) {
                continue;
            }

            if (!$this->isRedisCache($config)) {
                continue;
            }

            $targets['cache:' . $name] = function () use ($name): void {
                $key = 'audit_redis_' . str_replace('.', '', uniqid('', true));
                if (!Cache::write($key, true, $name) || Cache::read($key, $name) !== true) {
                    throw new RuntimeException(sprintf('Cache configuration [%s] did not accept a Redis probe.', $name));
                }

                Cache::delete($key, $name);
            };
        }

        if (class_exists(QueueManager::class)) {
            foreach (QueueManager::configured() as $name) {
                $config = QueueManager::getConfig($name);
                if (!is_array($config)) {
                    continue;
                }

                if (!$this->isRedisUrl($config['url'] ?? null)) {
                    continue;
                }

                $targets['queue:' . $name] = static function () use ($name): void {
                    QueueManager::engine($name);
                };
            }
        }

        $session = Configure::read('Session.handler');
        if (is_array($session) && ($session['engine'] ?? null) === 'Redis') {
            $targets['session'] = function () use ($session): void {
                if (!extension_loaded('redis') || !class_exists(Redis::class)) {
                    throw new RuntimeException('ext-redis is not loaded.');
                }

                $client = new Redis();
                $host = is_string($session['server'] ?? null) ? $session['server'] : '127.0.0.1';
                $port = is_int($session['port'] ?? null) ? $session['port'] : 6379;
                if (!$client->connect($host, $port, 1.0)) {
                    throw new RuntimeException(sprintf('Unable to connect to Redis at %s:%d.', $host, $port));
                }

                $client->ping();
                $client->close();
            };
        }

        return $targets;
    }

    /**
     * Determine whether a cache config uses Redis.
     *
     * @param array<string, mixed> $config Cache configuration.
     * @return bool
     */
    private function isRedisCache(array $config): bool
    {
        $className = $config['className'] ?? '';

        return is_string($className) && str_contains(strtolower($className), 'redis');
    }

    /**
     * Determine whether a queue URL uses Redis.
     *
     * @param mixed $url Queue URL.
     * @return bool
     */
    private function isRedisUrl(mixed $url): bool
    {
        if (is_string($url)) {
            return str_starts_with($url, 'redis:') || str_starts_with($url, 'rediss:');
        }

        if (!is_array($url)) {
            return false;
        }

        $transport = $url['transport'] ?? null;

        return is_string($transport)
            && (str_starts_with($transport, 'redis:') || str_starts_with($transport, 'rediss:'));
    }
}
