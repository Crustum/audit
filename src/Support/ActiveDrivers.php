<?php
declare(strict_types=1);

namespace Crustum\Audit\Support;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;

/**
 * Discovers active CakePHP infrastructure configurations.
 */
final class ActiveDrivers
{
    /**
     * Get configured cache names.
     *
     * @return list<string>
     */
    public static function cacheConfigurations(): array
    {
        return array_values(Cache::configured());
    }

    /**
     * Get configured database connection names.
     *
     * @return list<string>
     */
    public static function databaseConnections(): array
    {
        return array_values(ConnectionManager::configured());
    }

    /**
     * Get the configured session handler or defaults profile.
     *
     * @return string|null
     */
    public static function sessionHandler(): ?string
    {
        $handler = Configure::read('Session.handler');

        if (is_string($handler) && $handler !== '') {
            return $handler;
        }

        if (is_array($handler)) {
            $className = $handler['className'] ?? null;

            if (is_string($className) && $className !== '') {
                return $className;
            }
        }

        return Configured::string('Session.defaults');
    }
}
