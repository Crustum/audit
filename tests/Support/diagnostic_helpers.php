<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Security;
use Crustum\Audit\Support\Composer;

if (!function_exists('audit_environment_path')) {
    /**
     * Create a temporary diagnostic application directory.
     *
     * @return string
     */
    function audit_environment_path(): string
    {
        $path = TMP . 'audit-environment-' . str_replace('.', '', uniqid('', true));
        mkdir($path, 0775, true);

        return $path;
    }
}

if (!function_exists('audit_cake_core_path')) {
    /**
     * Create a temporary Cake core diagnostic directory.
     *
     * @return string
     */
    function audit_cake_core_path(): string
    {
        $path = TMP . 'audit-cake-core-' . str_replace('.', '', uniqid('', true));
        mkdir($path, 0775, true);

        return $path;
    }
}

if (!function_exists('without_application_secret')) {
    /**
     * Clear runtime SECURITY_SALT sources for a diagnostic scenario.
     *
     * @param callable(): void $callback Scenario body.
     * @return void
     */
    function without_application_secret(callable $callback): void
    {
        putenv('SECURITY_SALT');
        unset($_ENV['SECURITY_SALT'], $_SERVER['SECURITY_SALT']);
        Configure::delete('Security.salt');

        $reflection = new ReflectionProperty(Security::class, 'salt');
        $original = $reflection->getValue();
        $reflection->setValue(null, null);

        try {
            $callback();
        } finally {
            $reflection->setValue(null, $original);
            putenv('SECURITY_SALT');
            unset($_ENV['SECURITY_SALT'], $_SERVER['SECURITY_SALT']);
        }
    }
}

if (!function_exists('audit_composer_fixture')) {
    /**
     * Create a Composer diagnostic fixture.
     *
     * @param string $script Process script body.
     * @param bool $withLock Whether to create composer.lock.
     * @return array{path: string, composer: \Crustum\Audit\Support\Composer}
     */
    function audit_composer_fixture(string $script, bool $withLock = true): array
    {
        $path = TMP . 'audit-composer-' . str_replace('.', '', uniqid('', true));
        mkdir($path . DS . 'vendor', 0775, true);
        file_put_contents($path . DS . 'composer.json', '{}');
        if ($withLock) {
            file_put_contents($path . DS . 'composer.lock', '{}');
        }

        file_put_contents($path . DS . 'vendor' . DS . 'autoload.php', '<?php');
        $executable = $path . DS . 'composer.php';
        file_put_contents($executable, "<?php\n" . $script);

        return ['path' => $path, 'composer' => new Composer([PHP_BINARY, $executable], $path)];
    }
}

if (!function_exists('audit_migrations_connection')) {
    /**
     * Create an isolated SQLite connection for TestApp migration probes.
     *
     * @return array{0: string, 1: string}
     */
    function audit_migrations_connection(): array
    {
        $name = 'audit_mig_' . str_replace('.', '', uniqid('', true));
        $path = TMP . $name . '.sqlite';
        ConnectionManager::setConfig($name, [
            'className' => Connection::class,
            'driver' => Sqlite::class,
            'database' => $path,
        ]);

        return [$name, $path];
    }
}

if (!function_exists('audit_reset_environment_mode')) {
    /**
     * Reset Audit environment mode defaults used by diagnostic tests.
     *
     * @return void
     */
    function audit_reset_environment_mode(): void
    {
        Configure::write('Audit.environment', 'local');
        Configure::write('Audit.environments', [
            'local' => ['local'],
            'production' => ['production'],
        ]);
    }
}
