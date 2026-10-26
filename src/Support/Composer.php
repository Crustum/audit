<?php
declare(strict_types=1);

namespace Crustum\Audit\Support;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Creates bounded Composer processes for the audited application.
 */
final readonly class Composer
{
    /**
     * Explicit Composer command.
     *
     * @var list<string>|null
     */
    private ?array $composerCommand;

    /**
     * Working directory.
     *
     * @var string
     */
    private string $workingDirectory;

    /**
     * Constructor.
     *
     * @param list<string>|null $composerCommand Explicit Composer command.
     * @param string|null $workingDirectory Process working directory.
     */
    public function __construct(?array $composerCommand = null, ?string $workingDirectory = null)
    {
        $this->composerCommand = $composerCommand;
        $this->workingDirectory = $workingDirectory ?? ROOT;
    }

    /**
     * Get the Composer command for the application.
     *
     * @return list<string>
     */
    public function command(): array
    {
        if ($this->composerCommand !== null) {
            return $this->composerCommand;
        }

        $localComposer = $this->workingDirectory . DS . 'composer.phar';

        if (is_file($localComposer)) {
            return [PHP_BINARY, $localComposer];
        }

        $composer = new ExecutableFinder()->find('composer');

        if ($composer === null) {
            throw new RuntimeException('Unable to locate the Composer executable.');
        }

        return [$composer];
    }

    /**
     * Create a Composer process with an explicit timeout.
     *
     * @param list<string> $arguments Composer arguments.
     * @param float|null $timeout Maximum runtime in seconds.
     * @return \Symfony\Component\Process\Process
     */
    public function process(array $arguments, ?float $timeout = 60.0): Process
    {
        $process = new Process(
            [...$this->command(), ...$arguments],
            $this->workingDirectory,
        );
        $process->setTimeout($timeout);

        return $process;
    }
}
