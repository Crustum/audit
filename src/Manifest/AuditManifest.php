<?php
declare(strict_types=1);

namespace Crustum\Audit\Manifest;

use Crustum\Audit\AuditPlugin;
use Crustum\PluginManifest\Manifest\ManifestOps;

/**
 * Standalone manifest provider for the Audit plugin.
 *
 * Plain class without interface or trait dependencies, so the plugin loads
 * and runs without `crustum/plugin-manifest` installed. Discovered by the
 * installer through the provider convention when the plugin class itself
 * provides no manifest. All package references stay inside method bodies,
 * which only execute when the installer (and therefore the package) exists.
 */
final class AuditManifest
{
    /**
     * Plugin install assets via crustum/plugin-manifest.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function manifest(): array
    {
        $pluginPath = dirname(__DIR__, 2);
        $plugin = ManifestOps::pluginName(AuditPlugin::class);

        return array_merge(
            ManifestOps::manifestConfig(
                $plugin,
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'audit.php',
                CONFIG . 'audit.php',
                false,
            ),
            ManifestOps::manifestBootstrapAppend(
                $plugin,
                "if (file_exists(CONFIG . 'audit.php')) {\n    Configure::load('audit', 'default');\n}",
                '// Audit Plugin Configuration',
            ),
            ManifestOps::manifestStarRepo($plugin, 'Crustum/Audit'),
        );
    }
}
