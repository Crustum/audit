<?php
declare(strict_types=1);

namespace Crustum\Audit;

use Cake\Container\ContainerInterface;
use Cake\Core\Configure;
use Closure;
use Crustum\Audit\Contract\FixableInterface;
use Crustum\Audit\Result\DiagnosticFixOutcome;
use Crustum\Audit\Result\DiagnosticOutcome;
use Crustum\Audit\Result\DiagnosticReport;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\FixResult;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Registry and runner entry point for diagnostics.
 */
class AuditManager
{
    /**
     * Registered diagnostic classes.
     *
     * @var array<class-string<\Crustum\Audit\Diagnostic>, class-string<\Crustum\Audit\Diagnostic>>
     */
    protected array $diagnostics = [];

    /**
     * Constructor.
     *
     * @param \Cake\Container\ContainerInterface $container Application container.
     */
    public function __construct(protected ContainerInterface $container)
    {
    }

    /**
     * Register a diagnostic class.
     *
     * @param class-string $diagnostic Diagnostic class.
     * @return $this
     */
    public function diagnostic(string $diagnostic): self
    {
        if (!is_subclass_of($diagnostic, Diagnostic::class)) {
            throw new InvalidArgumentException('Diagnostics must extend [' . Diagnostic::class . '].');
        }

        $this->diagnostics[$diagnostic] = $diagnostic;

        return $this;
    }

    /**
     * Register diagnostic classes.
     *
     * @param iterable<array-key, class-string> $diagnostics Diagnostic classes.
     * @return $this
     */
    public function diagnostics(iterable $diagnostics): self
    {
        foreach ($diagnostics as $diagnostic) {
            $this->diagnostic($diagnostic);
        }

        return $this;
    }

    /**
     * Get registered diagnostic classes.
     *
     * @return list<class-string<\Crustum\Audit\Diagnostic>>
     */
    public function registered(): array
    {
        return array_values($this->diagnostics);
    }

    /**
     * Determine whether diagnostics are registered.
     *
     * @return bool
     */
    public function hasDiagnostics(): bool
    {
        return $this->diagnostics !== [];
    }

    /**
     * Run registered diagnostics.
     *
     * @return \Crustum\Audit\Result\DiagnosticReport
     */
    public function run(): DiagnosticReport
    {
        return $this->pendingRun()->run();
    }

    /**
     * Begin a constrained run.
     *
     * @param string ...$selectors Included selectors.
     * @return \Crustum\Audit\PendingRun
     */
    public function only(string ...$selectors): PendingRun
    {
        return $this->pendingRun()->only(...$selectors);
    }

    /**
     * Begin an excluding run.
     *
     * @param string ...$selectors Excluded selectors.
     * @return \Crustum\Audit\PendingRun
     */
    public function except(string ...$selectors): PendingRun
    {
        return $this->pendingRun()->except(...$selectors);
    }

    /**
     * Begin a run that stops after the first failure.
     *
     * @param bool $bail Whether to bail.
     * @return \Crustum\Audit\PendingRun
     */
    public function bail(bool $bail = true): PendingRun
    {
        return $this->pendingRun()->bail($bail);
    }

    /**
     * Begin an observed run.
     *
     * @param \Closure(string, \Closure(?\Closure, ?\Closure): void): void $callback Group observer.
     * @return \Crustum\Audit\PendingRun
     */
    public function observeUsing(Closure $callback): PendingRun
    {
        return $this->pendingRun()->observeUsing($callback);
    }

    /**
     * Begin a run with approved fixes.
     *
     * @param \Closure(\Crustum\Audit\Result\DiagnosticOutcome): (bool|string) $callback Fix decision.
     * @return \Crustum\Audit\PendingRun
     */
    public function fixUsing(Closure $callback): PendingRun
    {
        return $this->pendingRun()->fixUsing($callback);
    }

    /**
     * Begin a run with a pre-rerun callback.
     *
     * @param \Closure(): void $callback Callback.
     * @return \Crustum\Audit\PendingRun
     */
    public function beforeRerun(Closure $callback): PendingRun
    {
        return $this->pendingRun()->beforeRerun($callback);
    }

    /**
     * Run one diagnostic and contain exceptions.
     *
     * @param \Crustum\Audit\Diagnostic $diagnostic Diagnostic instance.
     * @return \Crustum\Audit\Result\DiagnosticOutcome
     */
    public function check(Diagnostic $diagnostic): DiagnosticOutcome
    {
        try {
            $result = $diagnostic->check();
        } catch (Throwable $throwable) {
            $result = DiagnosticResult::error($throwable->getMessage())
                ->withContext('exception', $throwable::class);
        }

        return new DiagnosticOutcome($diagnostic, $result);
    }

    /**
     * Apply a fix for an outcome.
     *
     * @param \Crustum\Audit\Result\DiagnosticOutcome $outcome Diagnostic outcome.
     * @param string|null $option Selected fix option.
     * @return \Crustum\Audit\Result\DiagnosticFixOutcome
     */
    public function fix(DiagnosticOutcome $outcome, ?string $option = null): DiagnosticFixOutcome
    {
        $diagnostic = $outcome->diagnostic;

        if (!$diagnostic instanceof FixableInterface) {
            throw new LogicException(sprintf(
                'Diagnostic [%s] does not implement [%s].',
                $diagnostic::class,
                FixableInterface::class,
            ));
        }

        if (!$outcome->fixable()) {
            throw new LogicException(sprintf(
                'Diagnostic outcome [%s] is not fixable.',
                $outcome->result->code ?? $diagnostic::class,
            ));
        }

        $options = $outcome->result->fixOptions;

        if ($option === null && $options !== []) {
            throw new LogicException(sprintf(
                'Diagnostic outcome [%s] requires a fix option.',
                $outcome->result->code ?? $diagnostic::class,
            ));
        }

        if ($option !== null && $options === []) {
            throw new LogicException(sprintf(
                'Diagnostic outcome [%s] does not accept a fix option.',
                $outcome->result->code ?? $diagnostic::class,
            ));
        }

        if ($option !== null && !array_key_exists($option, $options)) {
            throw new LogicException(sprintf(
                'Fix option [%s] is not declared for diagnostic outcome [%s].',
                $option,
                $outcome->result->code ?? $diagnostic::class,
            ));
        }

        try {
            $result = $diagnostic->fix($outcome->result, $option);
        } catch (Throwable $throwable) {
            $result = FixResult::error(
                sprintf('Failed to fix %s: %s', $diagnostic->name, $throwable->getMessage()),
            )->withContext('exception', $throwable::class);
        }

        return new DiagnosticFixOutcome($diagnostic, $result);
    }

    /**
     * Get selected diagnostics grouped in first-seen order.
     *
     * @param \Crustum\Audit\DiagnosticSelection|null $selection Selection.
     * @return array<string, list<\Crustum\Audit\Diagnostic>>
     */
    public function selectedByGroup(?DiagnosticSelection $selection = null): array
    {
        $grouped = [];

        foreach ($this->selected($selection) as $diagnostic) {
            $grouped[$diagnostic->group][] = $diagnostic;
        }

        return $grouped;
    }

    /**
     * Begin a pending run.
     *
     * @return \Crustum\Audit\PendingRun
     */
    protected function pendingRun(): PendingRun
    {
        return new PendingRun($this, $this->defaultSelection());
    }

    /**
     * Get configured default selection.
     *
     * @return \Crustum\Audit\DiagnosticSelection
     */
    protected function defaultSelection(): DiagnosticSelection
    {
        return DiagnosticSelection::make(
            only: $this->configuredSelectors('Audit.only'),
            except: $this->configuredSelectors('Audit.except'),
        );
    }

    /**
     * Get selected diagnostic instances in registration order.
     *
     * @param \Crustum\Audit\DiagnosticSelection|null $selection Selection.
     * @return list<\Crustum\Audit\Diagnostic>
     */
    protected function selected(?DiagnosticSelection $selection = null): array
    {
        $diagnostics = [];

        foreach ($this->diagnostics as $class) {
            $diagnostic = $this->container->get($class);

            if (!$selection instanceof DiagnosticSelection || $selection->matches($diagnostic)) {
                $diagnostics[] = $diagnostic;
            }
        }

        return $diagnostics;
    }

    /**
     * Get valid selector strings from configuration.
     *
     * @param string $key Configuration path.
     * @return list<string>
     */
    private function configuredSelectors(string $key): array
    {
        $configured = Configure::read($key, []);

        if (!is_array($configured)) {
            return [];
        }

        return array_values(array_filter($configured, is_string(...)));
    }
}
