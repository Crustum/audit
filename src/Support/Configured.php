<?php
declare(strict_types=1);

namespace Crustum\Audit\Support;

use Cake\Core\Configure;

/**
 * Defensive access to application configuration.
 */
final class Configured
{
    /**
     * Get a configured non-empty string.
     *
     * @param string $key Configuration path.
     * @param string|null $default Fallback value.
     * @return string|null
     */
    public static function string(string $key, ?string $default = null): ?string
    {
        $value = Configure::read($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * Get configuration keys without a usable value.
     *
     * @param list<string> $keys Configuration paths.
     * @return list<string>
     */
    public static function missing(array $keys): array
    {
        return array_values(array_filter(
            $keys,
            static fn(string $key): bool => self::isMissing(Configure::read($key)),
        ));
    }

    /**
     * Determine whether a configured value is missing.
     *
     * @param mixed $value Configured value.
     * @return bool
     */
    private static function isMissing(mixed $value): bool
    {
        return $value === null
            || (is_string($value) && trim($value) === '')
            || (is_array($value) && $value === []);
    }
}
