<?php
declare(strict_types=1);

namespace Crustum\Audit\Command;

use Bake\Command\BakeCommand;
use Bake\Utility\TemplateRenderer;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
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
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $name = $args->getArgumentAt(0);

        if (empty($name)) {
            $io->err('<error>You must provide a diagnostic name.</error>');
            $io->out('Example: bin/cake bake diagnostic HorizonIsRunning');

            return static::CODE_ERROR;
        }

        $name = $this->_getName($name);
        $fixable = $args->getOption('fixable') === true;
        $content = $this->getContent($name, $args, $io, $fixable);

        if (empty($content)) {
            $io->err("<warning>No generated content for '{$name}', not generating template.</warning>");

            return static::CODE_ERROR;
        }

        $this->bake($name, $args, $io, $content);

        return static::CODE_SUCCESS;
    }

    /**
     * Write the generated diagnostic class.
     *
     * @param string $name Diagnostic class name
     * @param \Cake\Console\Arguments $args CLI arguments
     * @param \Cake\Console\ConsoleIo $io Console io
     * @param string|true $content Content to write.
     * @return void
     */
    public function bake(string $name, Arguments $args, ConsoleIo $io, string|bool $content): void
    {
        $path = $this->getPath($args);
        $filename = $path . $name . '.php';
        $io->out("\n" . sprintf('Baking diagnostic class for %s...', $name), 1, ConsoleIo::QUIET);

        if (is_string($content) && $args->getOption('verbose')) {
            $io->out($content);
        }

        if (is_string($content)) {
            $forceOption = $args->getOption('force');
            $force = is_bool($forceOption) && $forceOption;
            $io->createFile($filename, $content, $force);
        }

        $emptyFile = $path . '.gitkeep';
        $this->deleteEmptyFile($emptyFile, $io);
    }

    /**
     * Get content for the diagnostic class.
     *
     * @param string $name Diagnostic class name
     * @param \Cake\Console\Arguments $args CLI arguments
     * @param \Cake\Console\ConsoleIo $io Console io
     * @param bool $fixable Whether to generate a fixable diagnostic.
     * @return string|bool Generated content
     */
    public function getContent(string $name, Arguments $args, ConsoleIo $io, bool $fixable = false): string|bool
    {
        $namespace = Configure::read('App.namespace');
        if ($this->plugin) {
            $namespace = $this->_pluginNamespace($this->plugin);
        }

        $vars = [
            'namespace' => $namespace,
            'class' => $name,
            'fixable' => $fixable,
        ];

        $themeOption = $args->getOption('theme');
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
     * @param \Cake\Console\Arguments $args Arguments instance to read the prefix option from.
     * @return string Path to output.
     */
    #[Override]
    public function getPath(Arguments $args): string
    {
        $path = APP . $this->pathFragment;
        if ($this->plugin) {
            $path = $this->_pluginPath($this->plugin) . 'src/' . $this->pathFragment;
        }

        $prefix = $this->getPrefix($args);
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
        $parser = $this->_setCommonOptions($parser);
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
