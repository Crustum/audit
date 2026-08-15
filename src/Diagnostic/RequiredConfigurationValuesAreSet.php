<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Cake\Core\Configure;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\Link;
use Crustum\Audit\Result\Message;
use Crustum\Audit\Support\Configured;
use Crustum\Audit\Support\Details;
use Override;

/**
 * Verifies host-declared required CakePHP configuration paths.
 */
final class RequiredConfigurationValuesAreSet extends Diagnostic
{
    public string $name = 'Config values are set';

    public string $group = 'configuration';

    /**
     * @inheritDoc
     */
    #[Override]
    protected function messages(): array
    {
        return [
            'set' => 'Every required configuration value is set.',
            'missing' => Message::make(
                'Some required configuration values are not set.',
                'Set the missing values through CakePHP configuration or deployment environment variables.',
            )->link(Link::docs('development/configuration', 'environment-variables')),
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        $required = Configure::read('Audit.requiredConfiguration', []);
        if (!is_array($required)) {
            $required = [];
        }

        $labels = [];
        foreach ($required as $key => $label) {
            if (is_string($key) && is_string($label)) {
                $labels[$key] = $label;
            } elseif (is_int($key) && is_string($label)) {
                $labels[$label] = 'application';
            }
        }

        $missing = array_intersect_key($labels, array_flip(Configured::missing(array_keys($labels))));
        if ($missing === []) {
            return $this->pass('set');
        }

        return $this->fail('missing')->withDetails(Details::bullets(array_map(
            static fn(string $key, string $feature): string => sprintf('%s (%s)', $key, $feature),
            array_keys($missing),
            $missing,
        )));
    }
}
