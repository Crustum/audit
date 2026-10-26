<?php
declare(strict_types=1);

namespace Crustum\Audit\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIoInterface;
use Cake\Console\ConsoleOptionParser;
use Cake\Console\Helper;
use Cake\Core\Configure;
use Closure;
use Crustum\Audit\AuditManager;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\PendingRun;
use Crustum\Audit\Renderer\AgentRenderer;
use Crustum\Audit\Renderer\CliRenderer;
use Crustum\Audit\Renderer\GithubRenderer;
use Crustum\Audit\Result\DiagnosticOutcome;
use Crustum\Audit\Result\DiagnosticReport;
use Crustum\Audit\Result\Status;
use Crustum\Prompts\Console\Helper\ConfirmHelper;
use Crustum\Prompts\Console\Helper\SelectHelper;
use Crustum\Prompts\Console\Helper\TaskHelper;
use Laravel\AgentDetector\AgentDetector;
use LogicException;
use Override;
use SignalHandler\Command\SignalableCommandInterface;
use SignalHandler\Command\Trait\SignalHandlerTrait;
use SignalHandler\Signal\Signal;

/**
 * Run Audit diagnostics and render reports.
 */
class AuditCommand extends Command implements SignalableCommandInterface
{
    use SignalHandlerTrait;

    /**
     * Select entry that declines a fix requiring a choice.
     */
    protected const SKIP_FIX = '__skip__';

    /**
     * Interactive CLI renderer.
     *
     * @var \Crustum\Audit\Renderer\CliRenderer|null
     */
    protected ?CliRenderer $renderer = null;

    /**
     * Active console IO for the current execution.
     *
     * @var \Cake\Console\ConsoleIoInterface|null
     */
    protected ?ConsoleIoInterface $consoleIo = null;

    /**
     * Constructor.
     *
     * @param \Crustum\Audit\AuditManager $manager Audit manager.
     */
    public function __construct(protected AuditManager $manager)
    {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'audit process';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Run Audit diagnostics.';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription(self::getDescription())
            ->addOption('only', [
                'help' => 'Run only the specified diagnostic classes, groups, or packages (comma-separated).',
                'default' => '',
            ])
            ->addOption('except', [
                'help' => 'Skip the specified diagnostic classes, groups, or packages (comma-separated).',
                'default' => '',
            ])
            ->addOption('bail', [
                'help' => 'Stop after the first failing diagnostic.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('fix', [
                'help' => 'Run available deterministic fixes without prompting.',
                'boolean' => true,
                'default' => false,
            ])
            ->addOption('format', [
                'help' => 'Output format: auto, cli, json, github, or agent.',
                'default' => 'auto',
                'choices' => ['auto', 'cli', 'json', 'github', 'agent'],
            ])
            ->addOption('fail-on', [
                'help' => 'Exit code threshold: fail, warn, or never.',
                'default' => 'fail',
                'choices' => ['fail', 'warn', 'never'],
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function execute(): ?int
    {
        $args = $this->args;
        $io = $this->io;
        $this->consoleIo = $io;
        $this->bindGracefulTermination(function (): void {
            $this->consoleIo?->err('Audit interrupted.');
        });

        try {
            $format = $this->format($args, $io);
            $failOn = $this->failOn($args, $io);

            if ($format === null || $failOn === null || !$this->fixOptionIsValidFor($args, $io, $format)) {
                return static::CODE_ERROR;
            }

            return match ($format) {
                'cli' => $this->runCli($args, $io, $failOn),
                'json' => $this->runJson($args, $io, $failOn),
                'github' => $this->runGithub($args, $io, $failOn),
                'agent' => $this->runAgent($args, $io, $failOn),
            };
        } finally {
            $this->ensureValidTimezoneForTermination();
            $this->unbindSignals();
            $this->consoleIo = null;
            $this->renderer = null;
        }
    }

    /**
     * Prevent an invalid application timezone from crashing command termination.
     *
     * @return void
     */
    protected function ensureValidTimezoneForTermination(): void
    {
        $timezone = Configure::read('App.defaultTimezone');

        if (is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
            return;
        }

        Configure::write('App.defaultTimezone', date_default_timezone_get());
    }

    /**
     * Get the requested output format.
     *
     * @param \Cake\Console\Arguments $args CLI arguments.
     * @param \Cake\Console\ConsoleIoInterface $io Console IO.
     * @return 'cli'|'json'|'github'|'agent'|null
     */
    protected function format(Arguments $args, ConsoleIoInterface $io): ?string
    {
        $format = $args->getOption('format');

        if (!is_string($format) || !in_array($format, ['auto', 'cli', 'json', 'github', 'agent'], true)) {
            $io->error('The --format option must be one of: auto, cli, json, github, agent.');

            return null;
        }

        if ($format === 'auto') {
            return AgentDetector::detect()->isAgent ? 'agent' : 'cli';
        }

        return $format;
    }

    /**
     * Get the configured exit-code threshold.
     *
     * @param \Cake\Console\Arguments $args CLI arguments.
     * @param \Cake\Console\ConsoleIoInterface $io Console IO.
     * @return 'fail'|'warn'|'never'|null
     */
    protected function failOn(Arguments $args, ConsoleIoInterface $io): ?string
    {
        $failOn = $args->getOption('fail-on');

        if (!is_string($failOn) || !in_array($failOn, [Status::Fail->value, Status::Warn->value, 'never'], true)) {
            $io->error('The --fail-on option must be one of: fail, warn, never.');

            return null;
        }

        return $failOn;
    }

    /**
     * Determine whether the fix option is valid for the format.
     *
     * @param \Cake\Console\Arguments $args CLI arguments.
     * @param \Cake\Console\ConsoleIoInterface $io Console IO.
     * @param 'cli'|'json'|'github'|'agent' $format Output format.
     * @return bool
     */
    protected function fixOptionIsValidFor(Arguments $args, ConsoleIoInterface $io, string $format): bool
    {
        if ($format === 'cli' || $format === 'agent' || !$args->getOption('fix')) {
            return true;
        }

        $io->error('The --fix option may only be used with the cli or agent output formats.');

        return false;
    }

    /**
     * Run Audit for CLI output.
     *
     * @param \Cake\Console\Arguments $args CLI arguments.
     * @param \Cake\Console\ConsoleIoInterface $io Console IO.
     * @param 'fail'|'warn'|'never' $failOn Exit threshold.
     * @return int
     */
    protected function runCli(Arguments $args, ConsoleIoInterface $io, string $failOn): int
    {
        $runner = $this->runner($args);

        if ($this->shouldUseTasks()) {
            $runner->observeUsing($this->taskObserver($io));
        }

        $report = $runner
            ->fixUsing(fn(DiagnosticOutcome $outcome): bool|string => $this->fixDecision($args, $io, $outcome))
            ->beforeRerun(function () use ($io): void {
                $this->helper($io, 'Info')->output([
                    'message' => 'Re-running diagnostics after applying fixes...',
                ]);
            })
            ->run();

        if ($report->diagnostics() === []) {
            if (!$this->manager->hasDiagnostics()) {
                $this->helper($io, 'Info')->output(['message' => 'No diagnostics registered.']);
            } else {
                $this->helper($io, 'Warning')->output([
                    'message' => 'No diagnostics matched the selected filters.',
                ]);
            }

            return static::CODE_SUCCESS;
        }

        $this->renderer($io)->render($report);

        return $this->exitCode($report, $failOn);
    }

    /**
     * Run Audit for JSON output.
     *
     * @param \Cake\Console\Arguments $args CLI arguments.
     * @param \Cake\Console\ConsoleIoInterface $io Console IO.
     * @param 'fail'|'warn'|'never' $failOn Exit threshold.
     * @return int
     */
    protected function runJson(Arguments $args, ConsoleIoInterface $io, string $failOn): int
    {
        $report = $this->runner($args)->run();
        $io->out(json_encode($report, JSON_THROW_ON_ERROR));

        return $this->exitCode($report, $failOn);
    }

    /**
     * Run Audit for GitHub Actions output.
     *
     * @param \Cake\Console\Arguments $args CLI arguments.
     * @param \Cake\Console\ConsoleIoInterface $io Console IO.
     * @param 'fail'|'warn'|'never' $failOn Exit threshold.
     * @return int
     */
    protected function runGithub(Arguments $args, ConsoleIoInterface $io, string $failOn): int
    {
        $report = $this->runner($args)->run();

        foreach (new GithubRenderer()->annotations($report) as $annotation) {
            $io->out($annotation);
        }

        return $this->exitCode($report, $failOn);
    }

    /**
     * Run Audit for agent-optimized output.
     *
     * @param \Cake\Console\Arguments $args CLI arguments.
     * @param \Cake\Console\ConsoleIoInterface $io Console IO.
     * @param 'fail'|'warn'|'never' $failOn Exit threshold.
     * @return int
     */
    protected function runAgent(Arguments $args, ConsoleIoInterface $io, string $failOn): int
    {
        $runner = $this->runner($args);

        if ($args->getOption('fix')) {
            $runner->fixUsing(
                static fn(DiagnosticOutcome $outcome): bool => !$outcome->fixRequiresOption(),
            );
        }

        $report = $runner->run();
        $io->out(new AgentRenderer()->render($report));

        return $this->exitCode($report, $failOn);
    }

    /**
     * Begin a run configured from the command options.
     *
     * @param \Cake\Console\Arguments $args CLI arguments.
     * @return \Crustum\Audit\PendingRun
     */
    protected function runner(Arguments $args): PendingRun
    {
        return $this->manager
            ->only(...$this->optionList($args, 'only'))
            ->except(...$this->optionList($args, 'except'))
            ->bail((bool)$args->getOption('bail'));
    }

    /**
     * Get a normalized list option.
     *
     * @param \Cake\Console\Arguments $args CLI arguments.
     * @param string $name Option name.
     * @return list<string>
     */
    protected function optionList(Arguments $args, string $name): array
    {
        $value = $args->getOption($name);

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            trim(...),
            explode(',', $value),
        ), static fn(string $item): bool => $item !== ''));
    }

    /**
     * Determine whether Crustum Prompts tasks should be used.
     *
     * @return bool
     */
    protected function shouldUseTasks(): bool
    {
        return function_exists('pcntl_fork');
    }

    /**
     * Get the callback that observes each diagnostic group as a console task.
     *
     * @param \Cake\Console\ConsoleIoInterface $io Console IO.
     * @return \Closure(string, \Closure): void
     */
    protected function taskObserver(ConsoleIoInterface $io): Closure
    {
        return function (string $group, Closure $next) use ($io): void {
            $this->taskHelper($io)->run([
                'label' => sprintf('%s diagnostics', ucfirst($group)),
                'limit' => 0,
                'keepSummary' => true,
                'callback' => function (object $logger) use ($next): void {
                    $this->observeGroupTask($next, $logger);
                },
            ]);
        };
    }

    /**
     * Observe a diagnostic group within a console task.
     *
     * @param \Closure $next Group continuation.
     * @param object $logger Task logger.
     * @return void
     */
    protected function observeGroupTask(Closure $next, object $logger): void
    {
        $next(
            function (Diagnostic $diagnostic) use ($logger): void {
                if (method_exists($logger, 'subLabel')) {
                    $logger->subLabel($diagnostic->name);
                }
            },
            function (DiagnosticOutcome $outcome) use ($logger): void {
                $this->logOutcome($logger, $outcome);
            },
        );

        if (method_exists($logger, 'subLabel')) {
            $logger->subLabel('');
        }
    }

    /**
     * Write a diagnostic outcome to the task logger.
     *
     * @param object $logger Task logger.
     * @param \Crustum\Audit\Result\DiagnosticOutcome $outcome Diagnostic outcome.
     * @return void
     */
    protected function logOutcome(object $logger, DiagnosticOutcome $outcome): void
    {
        $name = $outcome->diagnostic->name;

        match ($outcome->result->status) {
            Status::Pass => method_exists($logger, 'success') ? $logger->success($name) : null,
            Status::Notice, Status::Skip => method_exists($logger, 'line')
                ? $logger->line($name . ': ' . $outcome->result->summary)
                : null,
            Status::Warn => method_exists($logger, 'warning')
                ? $logger->warning($name . ': ' . $outcome->result->summary)
                : null,
            Status::Fail, Status::Error => method_exists($logger, 'error')
                ? $logger->error($name . ': ' . $outcome->result->summary)
                : null,
        };
    }

    /**
     * Decide whether a fix should be applied and with which option.
     *
     * @param \Cake\Console\Arguments $args CLI arguments.
     * @param \Cake\Console\ConsoleIoInterface $io Console IO.
     * @param \Crustum\Audit\Result\DiagnosticOutcome $outcome Diagnostic outcome.
     * @return string|bool
     */
    protected function fixDecision(Arguments $args, ConsoleIoInterface $io, DiagnosticOutcome $outcome): bool|string
    {
        if ($outcome->fixRequiresOption()) {
            return $this->chooseFix($io, $args, $outcome);
        }

        if ($args->getOption('fix')) {
            return true;
        }

        if ($args->getOption('quiet')) {
            return false;
        }

        $this->renderer($io)->renderFixConfirmation($outcome);

        return $this->confirmHelper($io)->run([
            'label' => $this->confirmationPrompt($outcome),
            'default' => true,
        ]);
    }

    /**
     * Let the user choose between the fix options or decline the fix.
     *
     * @param \Cake\Console\ConsoleIoInterface $io Console IO.
     * @param \Cake\Console\Arguments $args CLI arguments.
     * @param \Crustum\Audit\Result\DiagnosticOutcome $outcome Diagnostic outcome.
     * @return string|bool
     */
    protected function chooseFix(ConsoleIoInterface $io, Arguments $args, DiagnosticOutcome $outcome): bool|string
    {
        if ($args->getOption('quiet') || $args->getOption('fix')) {
            return false;
        }

        $this->renderer($io)->renderFixConfirmation($outcome);

        $choice = $this->selectHelper($io)->run([
            'label' => $this->confirmationPrompt($outcome),
            'options' => [
                ...$outcome->result->fixOptions,
                self::SKIP_FIX => $outcome->result->fixDeclineLabel ?? 'Skip — leave unfixed',
            ],
        ]);

        return $choice === self::SKIP_FIX ? false : (string)$choice;
    }

    /**
     * Get the confirmation prompt for a diagnostic fix.
     *
     * @param \Crustum\Audit\Result\DiagnosticOutcome $outcome Diagnostic outcome.
     * @return string
     */
    protected function confirmationPrompt(DiagnosticOutcome $outcome): string
    {
        return $outcome->result->confirmation
            ?? ($outcome->fixRequiresOption()
                ? sprintf('How should "%s" be fixed?', $outcome->diagnostic->name)
                : sprintf('Fix "%s"?', $outcome->diagnostic->name));
    }

    /**
     * Get the interactive CLI renderer.
     *
     * @param \Cake\Console\ConsoleIoInterface $io Console IO.
     * @return \Crustum\Audit\Renderer\CliRenderer
     */
    protected function renderer(ConsoleIoInterface $io): CliRenderer
    {
        return $this->renderer ??= new CliRenderer($io);
    }

    /**
     * Determine the command exit code for the report.
     *
     * @param \Crustum\Audit\Result\DiagnosticReport $report Diagnostic report.
     * @param 'fail'|'warn'|'never' $failOn Exit threshold.
     * @return int
     */
    protected function exitCode(DiagnosticReport $report, string $failOn): int
    {
        if ($failOn === 'never') {
            return static::CODE_SUCCESS;
        }

        if ($failOn === Status::Warn->value) {
            return $report->hasFailures() || $report->hasWarnings()
                ? static::CODE_ERROR
                : static::CODE_SUCCESS;
        }

        return $report->hasFailures() ? static::CODE_ERROR : static::CODE_SUCCESS;
    }

    /**
     * Load a Crustum Prompts console helper.
     *
     * @param \Cake\Console\ConsoleIoInterface $io Console IO.
     * @param string $name Helper short name.
     * @return \Cake\Console\Helper
     */
    protected function helper(ConsoleIoInterface $io, string $name): Helper
    {
        return $io->helper('Crustum/Prompts.' . $name);
    }

    /**
     * Resolve the Confirm helper.
     *
     * @param \Cake\Console\ConsoleIoInterface $io Console IO.
     * @return \Crustum\Prompts\Console\Helper\ConfirmHelper
     */
    protected function confirmHelper(ConsoleIoInterface $io): ConfirmHelper
    {
        $helper = $this->helper($io, 'Confirm');
        if (!$helper instanceof ConfirmHelper) {
            throw new LogicException('Expected Crustum/Prompts.Confirm helper.');
        }

        return $helper;
    }

    /**
     * Resolve the Select helper.
     *
     * @param \Cake\Console\ConsoleIoInterface $io Console IO.
     * @return \Crustum\Prompts\Console\Helper\SelectHelper
     */
    protected function selectHelper(ConsoleIoInterface $io): SelectHelper
    {
        $helper = $this->helper($io, 'Select');
        if (!$helper instanceof SelectHelper) {
            throw new LogicException('Expected Crustum/Prompts.Select helper.');
        }

        return $helper;
    }

    /**
     * Resolve the Task helper.
     *
     * @param \Cake\Console\ConsoleIoInterface $io Console IO.
     * @return \Crustum\Prompts\Console\Helper\TaskHelper
     */
    protected function taskHelper(ConsoleIoInterface $io): TaskHelper
    {
        $helper = $this->helper($io, 'Task');
        if (!$helper instanceof TaskHelper) {
            throw new LogicException('Expected Crustum/Prompts.Task helper.');
        }

        return $helper;
    }

    /**
     * @inheritDoc
     */
    public function getSubscribedSignals(): array
    {
        return [Signal::SIGINT, Signal::SIGTERM];
    }

    /**
     * @inheritDoc
     */
    public function onInterrupt(): int|false
    {
        return static::CODE_ERROR;
    }

    /**
     * @inheritDoc
     */
    public function onTerminateSignal(): int|false
    {
        return static::CODE_ERROR;
    }
}
