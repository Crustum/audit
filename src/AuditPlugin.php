<?php
declare(strict_types=1);

namespace Crustum\Audit;

use Bake\Command\BakeCommand;
use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\Plugin;
use Cake\Core\PluginApplicationInterface;
use Cake\Queue\QueueManager;
use Crustum\Audit\Command\AuditCommand;
use Crustum\Audit\Command\DiagnosticCommand;
use Crustum\Audit\Diagnostic\ApplicationSecretIsSet;
use Crustum\Audit\Diagnostic\ApplicationTimezoneIsValid;
use Crustum\Audit\Diagnostic\CacheConfigIsReachable;
use Crustum\Audit\Diagnostic\ComposerAuditPasses;
use Crustum\Audit\Diagnostic\ComposerAutoloadIsValid;
use Crustum\Audit\Diagnostic\ComposerLockIsFresh;
use Crustum\Audit\Diagnostic\ConfigurationFilesCanBeLoaded;
use Crustum\Audit\Diagnostic\DatabaseConnectionIsReachable;
use Crustum\Audit\Diagnostic\DebugModeMatchesEnvironment;
use Crustum\Audit\Diagnostic\EnvironmentFileExists;
use Crustum\Audit\Diagnostic\EnvironmentFileIsGitIgnored;
use Crustum\Audit\Diagnostic\MigrationsAreUpToDate;
use Crustum\Audit\Diagnostic\PhpVersionSatisfiesComposerRequirement;
use Crustum\Audit\Diagnostic\QueueConnectionIsAsynchronous;
use Crustum\Audit\Diagnostic\QueueConnectionIsReachable;
use Crustum\Audit\Diagnostic\RecommendedPhpExtensionsAreLoaded;
use Crustum\Audit\Diagnostic\RedisConnectionsAreReachable;
use Crustum\Audit\Diagnostic\RequiredConfigurationValuesAreSet;
use Crustum\Audit\Diagnostic\RequiredPhpExtensionsAreLoaded;
use Crustum\Audit\Diagnostic\ScheduledTasksRequireScheduler;
use Crustum\Audit\Diagnostic\SessionConfigIsReachable;
use Crustum\Audit\Diagnostic\SqliteDatabaseExists;
use Crustum\Audit\Diagnostic\StorageIsWritable;
use Crustum\Audit\Support\Composer;
use Crustum\Audit\Support\ComposerJson;
use Crustum\Prompts\PromptsPlugin;
use Migrations\Migrations;
use Override;
use SignalHandler\SignalHandlerPlugin;

/**
 * Plugin for Crustum/Audit
 */
class AuditPlugin extends BasePlugin
{
    /**
     * Plugin name.
     *
     * @var string|null
     */
    protected ?string $name = 'Crustum/Ignis';

    /**
     * @inheritDoc
     */
    #[Override]
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        if (class_exists(PromptsPlugin::class) && !Plugin::isLoaded('Crustum/Prompts')) {
            $app->addPlugin('Crustum/Prompts');
        }

        if (class_exists(SignalHandlerPlugin::class) && !Plugin::isLoaded('SignalHandler')) {
            $app->addPlugin('SignalHandler');
        }

        if (!Configure::check('Audit')) {
            if (file_exists(CONFIG . 'audit.php')) {
                Configure::load('audit', 'default');
            } elseif (file_exists($this->getConfigPath() . 'audit.php')) {
                Configure::load('Crustum/Audit.audit', 'default', false);
            }
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands->add('audit process', AuditCommand::class);

        if (class_exists(DiagnosticCommand::class) && class_exists(BakeCommand::class)) {
            $commands->add('bake diagnostic', DiagnosticCommand::class);
        }

        return $commands;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function services(ContainerInterface $container): void
    {
        $diagnostics = $this->defaultDiagnostics();

        $container->addShared(ComposerJson::class);
        $container->addShared(Composer::class);

        foreach ($diagnostics as $diagnostic) {
            $definition = $container->addShared($diagnostic);
            $arguments = $this->diagnosticArguments($diagnostic);
            if ($arguments !== []) {
                $definition->addArguments($arguments);
            }
        }

        $container->addShared(AuditManager::class, fn(): AuditManager => (new AuditManager($container))->diagnostics($diagnostics));
        $container->add(AuditCommand::class)->addArgument(AuditManager::class);
    }

    /**
     * Explicit constructor service arguments for a diagnostic class.
     *
     * @param class-string<\Crustum\Audit\Diagnostic> $diagnostic Diagnostic class.
     * @return list<class-string>
     */
    protected function diagnosticArguments(string $diagnostic): array
    {
        return match ($diagnostic) {
            PhpVersionSatisfiesComposerRequirement::class,
            RequiredPhpExtensionsAreLoaded::class,
            RecommendedPhpExtensionsAreLoaded::class => [ComposerJson::class],
            ComposerAutoloadIsValid::class,
            ComposerLockIsFresh::class,
            ComposerAuditPasses::class => [Composer::class],
            default => [],
        };
    }

    /**
     * Default diagnostic classes registered by the plugin.
     *
     * @return list<class-string<\Crustum\Audit\Diagnostic>>
     */
    public function defaultDiagnostics(): array
    {
        $diagnostics = [
            EnvironmentFileExists::class,
            ApplicationSecretIsSet::class,
            PhpVersionSatisfiesComposerRequirement::class,
            RequiredPhpExtensionsAreLoaded::class,
            RecommendedPhpExtensionsAreLoaded::class,
            ApplicationTimezoneIsValid::class,
            ComposerAutoloadIsValid::class,
            ComposerLockIsFresh::class,
            ConfigurationFilesCanBeLoaded::class,
            RequiredConfigurationValuesAreSet::class,
            DatabaseConnectionIsReachable::class,
            SqliteDatabaseExists::class,
            CacheConfigIsReachable::class,
            RedisConnectionsAreReachable::class,
            SessionConfigIsReachable::class,
            ScheduledTasksRequireScheduler::class,
            StorageIsWritable::class,
            DebugModeMatchesEnvironment::class,
            EnvironmentFileIsGitIgnored::class,
            ComposerAuditPasses::class,
        ];

        if (class_exists(Migrations::class)) {
            $diagnostics[] = MigrationsAreUpToDate::class;
        }

        if (class_exists(QueueManager::class)) {
            $diagnostics[] = QueueConnectionIsReachable::class;
            $diagnostics[] = QueueConnectionIsAsynchronous::class;
        }

        return $diagnostics;
    }
}
