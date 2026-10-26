<?php
declare(strict_types=1);

namespace Crustum\Audit\Command;

use Bake\Command\BakeCommand;
use Bake\Utility\TemplateRenderer;
use Cake\Console\ConsoleIoInterface;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Override;

/**
 * Bake command for generating Audit diagnostic classes.
 *
 * Usage:
 * ```
 * bin/cake bake diagnostic HorizonIsRunning
 * bin/cake bake diagnostic HorizonIsRunning --fixable
 * ```
 */
class DiagnosticCommand extends BakeCommand
{
    /**
     * Path fragment under APP or plugin src.
     *
     * @var string
     */
    public string $pathFragment = 'Diagnostic/';

    /**
     * Execute the command.
     *
     * @return int|null The exit code or null for success
     */
    public function execute(): ?int
    {
        $args = $this->args;
        $io = $this->io;
        $name = $args->getArgumentAt(0);

        if (empty($name)) {
            $io->err('<error>You must provide a diagnostic name.</error>');
            $io->out('Example: bin/cake bake diagnostic HorizonIsRunning');

            return static::CODE_ERROR;
        }

        $name = $this->getNameWithoutPrefix($name);
        $fixable = $args->getOption('fixable') === true;
        $content = $this->getContent($name, $fixable);

        if (empty($content)) {
            $io->err("<warning>No generated content for '{$name}', not generating template.</warning>");

            return static::CODE_ERROR;
        }

        $this->bake($name, $content);

        return static::CODE_SUCCESS;
    }

    /**
     * Write the generated diagnostic class.
     *
     * @param string $name Diagnostic class name
     * @param string|true $content Content to write.
     * @return void
     */
    public function bake(string $name, string|bool $content): void
    {
        $path = $this->getPath();
        $filename = $path . $name . '.php';
        $this->io->out("\n" . sprintf('Baking diagnostic class for %s...', $name), 1, ConsoleIoInterface::QUIET);

        if (is_string($content) && $this->args->getOption('verbose')) {
            $this->io->out($content);
        }

        if (is_string($content)) {
            $forceOption = $this->args->getOption('force');
            $force = is_bool($forceOption) && $forceOption;
            $this->io->createFile($filename, $content, $force);
        }

        $emptyFile = $path . '.gitkeep';
        $this->deleteEmptyFile($emptyFile);
    }

    /**
     * Get content for the diagnostic class.
     *
     * @param string $name Diagnostic class name
     * @param bool $fixable Whether to generate a fixable diagnostic.
     * @return string|bool Generated content
     */
    public function getContent(string $name, bool $fixable = false): string|bool
    {
        $namespace = Configure::read('App.namespace');
        if ($this->plugin) {
            $namespace = $this->pluginNamespace($this->plugin);
        }

        $vars = [
            'namespace' => $namespace,
            'class' => $name,
            'fixable' => $fixable,
        ];

        $themeOption = $this->args->getOption('theme');
        $theme = is_string($themeOption) ? $themeOption : null;
        $renderer = new TemplateRenderer($theme);
        $renderer->set('plugin', $this->plugin);
        $renderer->set($vars);

        $template = $fixable
            ? 'Crustum/Audit.Diagnostic/fixable_diagnostic'
            : 'Crustum/Audit.Diagnostic/diagnostic';

        return $renderer->generate($template);
    }

    /**
     * Gets the path for output.
     *
     * @return string Path to output.
     */
    #[Override]
    public function getPath(): string
    {
        $path = APP . $this->pathFragment;
        if ($this->plugin) {
            $path = $this->pluginPath($this->plugin) . 'src/' . $this->pathFragment;
        }

        $prefix = $this->getPrefix();
        if ($prefix !== '' && $prefix !== '0') {
            $path .= $prefix . DIRECTORY_SEPARATOR;
        }

        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Gets the option parser instance and configures it.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to configure
     * @return \Cake\Console\ConsoleOptionParser
     */
    #[Override]
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = $this->setCommonOptions($parser);
        $parser->setDescription(self::getDescription())
            ->addArgument('name', [
                'help' => 'Name of the diagnostic class to generate (e.g., HorizonIsRunning).',
                'required' => true,
            ])
            ->addOption('fixable', [
                'help' => 'Generate a diagnostic that can fix the failure it detects.',
                'boolean' => true,
                'default' => false,
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'bake diagnostic';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Bake an Audit diagnostic class.';
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return 'diagnostic';
    }
}
