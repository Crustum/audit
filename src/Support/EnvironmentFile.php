<?php
declare(strict_types=1);

namespace Crustum\Audit\Support;

/**
 * Resolves CakePHP environment file locations.
 *
 * Cake apps keep `.env` under `config/` (`CONFIG`), not the application root.
 */
final class EnvironmentFile
{
    /**
     * Directory that should contain `.env` files.
     *
     * Explicit `$basePath` values are treated as the environment directory so
     * tests can supply a temporary folder. Runtime defaults use `CONFIG`.
     *
     * @param string|null $basePath Override directory or application root for tests.
     * @return string
     */
    public static function directory(?string $basePath = null): string
    {
        if ($basePath !== null) {
            return rtrim($basePath, '/\\');
        }

        return rtrim(CONFIG, '/\\');
    }

    /**
     * Absolute path to an environment file.
     *
     * @param string $name File name such as `.env` or `.env.example`.
     * @param string|null $basePath Override directory.
     * @return string
     */
    public static function path(string $name = '.env', ?string $basePath = null): string
    {
        return self::directory($basePath) . DS . $name;
    }
}
