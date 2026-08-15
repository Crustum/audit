<?php
declare(strict_types=1);

namespace Crustum\Audit;

use Cake\Core\Configure;
use Crustum\Audit\Support\Configured;
use InvalidArgumentException;

/**
 * Safety mode used when evaluating diagnostic fixes.
 */
enum EnvironmentMode: string
{
    case Local = 'local';
    case Production = 'production';

    /**
     * Resolve the current Audit environment mode.
     *
     * @return self
     */
    public static function current(): self
    {
        return self::fromApplicationEnvironment(
            Configured::string('Audit.environment', 'production'),
        );
    }

    /**
     * Resolve a mode from an application environment name.
     *
     * @param string $environment Application environment name.
     * @return self
     */
    public static function fromApplicationEnvironment(string $environment): self
    {
        return self::configuredEnvironments()[$environment] ?? self::Production;
    }

    /**
     * Get the mode for each configured application environment name.
     *
     * @return array<string, self>
     */
    protected static function configuredEnvironments(): array
    {
        $environmentsByMode = Configure::read('Audit.environments', []);

        if (!is_array($environmentsByMode)) {
            throw new InvalidArgumentException(sprintf(
                'Audit environment modes must be an array, [%s] given.',
                get_debug_type($environmentsByMode),
            ));
        }

        $configured = [];

        foreach ($environmentsByMode as $mode => $environments) {
            $resolvedMode = is_string($mode) ? self::tryFrom($mode) : null;

            if ($resolvedMode === null) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid Audit environment mode [%s] configured.',
                    is_string($mode) ? $mode : get_debug_type($mode),
                ));
            }

            if (!is_array($environments)) {
                throw new InvalidArgumentException(sprintf(
                    'Application environment names for Audit mode [%s] must be an array, [%s] given.',
                    $mode,
                    get_debug_type($environments),
                ));
            }

            foreach ($environments as $environment) {
                if (!is_string($environment) || $environment === '') {
                    throw new InvalidArgumentException(sprintf(
                        'Application environment names for Audit mode [%s] must be non-empty strings.',
                        $mode,
                    ));
                }

                if (isset($configured[$environment]) && $configured[$environment] !== $resolvedMode) {
                    throw new InvalidArgumentException(sprintf(
                        'Application environment [%s] is configured for multiple Audit modes.',
                        $environment,
                    ));
                }

                $configured[$environment] = $resolvedMode;
            }
        }

        return $configured;
    }

    /**
     * Determine whether this mode uses production expectations.
     *
     * @return bool
     */
    public function isProduction(): bool
    {
        return $this === self::Production;
    }
}
