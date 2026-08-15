<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Cake\Cache\Cache;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\Link;
use Crustum\Audit\Result\Message;
use Crustum\Audit\Support\Details;
use Override;
use Throwable;

/**
 * Probes configured CakePHP cache services.
 *
 * Defaults to CakePHP 5.4 framework configs `_cake_translations_` and
 * `_cake_model_` (replacing deprecated `_cake_core_`).
 */
final class CacheConfigIsReachable extends Diagnostic
{
    public string $name = 'Cache connects';

    public string $group = 'cache';

    /**
     * Cache configuration names to probe.
     *
     * @var list<string>
     */
    private readonly array $configs;

    /**
     * Constructor.
     *
     * @param list<string>|string $configs Cache configuration name(s).
     */
    public function __construct(array|string $configs = ['_cake_translations_', '_cake_model_'])
    {
        parent::__construct();
        $this->configs = is_string($configs) ? [$configs] : $configs;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function messages(): array
    {
        return [
            'not-configured' => 'None of the application cache configurations [{config}] are configured.',
            'unreachable' => Message::make(
                'The application cannot reach every probed cache configuration.',
                'Check the CakePHP cache engine configuration.',
            )->link(Link::docs('core-libraries/caching', 'configuring-cache-class')),
            'reachable' => 'The application can reach every probed cache configuration.',
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        $configured = array_values(array_filter(
            $this->configs,
            static fn(string $config): bool => in_array($config, Cache::configured(), true),
        ));
        $label = implode(', ', $this->configs);

        if ($configured === []) {
            return $this->skip('not-configured', ['config' => $label]);
        }

        $failures = [];
        foreach ($configured as $config) {
            $key = 'audit_probe_' . str_replace('.', '', uniqid('', true));
            try {
                if (!Cache::write($key, true, $config) || Cache::read($key, $config) !== true) {
                    $failures[$config] = sprintf('Cache configuration [%s] did not accept a probe write/read.', $config);
                }
            } catch (Throwable $throwable) {
                $failures[$config] = $throwable->getMessage();
            } finally {
                try {
                    Cache::delete($key, $config);
                } catch (Throwable) {
                }
            }
        }

        return $failures === []
            ? $this->pass('reachable')
            : $this->fail('unreachable')->withDetails(Details::failures($failures));
    }
}
