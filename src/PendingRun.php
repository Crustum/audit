<?php
declare(strict_types=1);

namespace Crustum\Audit;

use Closure;
use Crustum\Audit\Result\DiagnosticOutcome;
use Crustum\Audit\Result\DiagnosticReport;
use LogicException;

/**
 * Configures and executes one manager run.
 *
 * @phpstan-type GroupContinuation \Closure(
 *     (\Closure(\Crustum\Audit\Diagnostic): void)|null,
 *     (\Closure(\Crustum\Audit\Result\DiagnosticOutcome): void)|null
 * ): void
 */
class PendingRun
{
    /**
     * Group observer.
     *
     * @var (\Closure(string, GroupContinuation): void)|null
     */
    protected ?Closure $observer = null;

    /**
     * Fix decision callback.
     *
     * @var (\Closure(\Crustum\Audit\Result\DiagnosticOutcome): (bool|string))|null
     */
    protected ?Closure $fix = null;

    /**
     * Callback invoked before diagnostics are rerun.
     *
     * @var (\Closure(): void)|null
     */
    protected ?Closure $beforeRerun = null;

    /**
     * Whether to stop after the first failure.
     *
     * @var bool
     */
    protected bool $bail = false;

    /**
     * Constructor.
     *
     * @param \Crustum\Audit\AuditManager $manager Audit manager.
     * @param \Crustum\Audit\DiagnosticSelection $selection Initial selection.
     */
    public function __construct(
        protected AuditManager $manager,
        protected DiagnosticSelection $selection,
    ) {
    }

    /**
     * Constrain the run to matching selectors.
     *
     * @param string ...$selectors Included selectors.
     * @return self
     */
    public function only(string ...$selectors): self
    {
        $this->selection = $this->selection->constrain(only: $selectors);

        return $this;
    }

    /**
     * Exclude matching selectors.
     *
     * @param string ...$selectors Excluded selectors.
     * @return self
     */
    public function except(string ...$selectors): self
    {
        $this->selection = $this->selection->constrain(except: $selectors);

        return $this;
    }

    /**
     * Stop after the first failure.
     *
     * @param bool $bail Whether to bail.
     * @return self
     */
    public function bail(bool $bail = true): self
    {
        $this->bail = $bail;

        return $this;
    }

    /**
     * Observe each diagnostic group.
     *
     * @param \Closure(string, GroupContinuation): void $callback Group observer.
     * @return self
     */
    public function observeUsing(Closure $callback): self
    {
        $this->observer = $callback;

        return $this;
    }

    /**
     * Apply fixes approved by a callback.
     *
     * @param \Closure(\Crustum\Audit\Result\DiagnosticOutcome): (bool|string) $callback Fix decision.
     * @return self
     */
    public function fixUsing(Closure $callback): self
    {
        $this->fix = $callback;

        return $this;
    }

    /**
     * Invoke a callback before rerunning after fixes.
     *
     * @param \Closure(): void $callback Callback.
     * @return self
     */
    public function beforeRerun(Closure $callback): self
    {
        $this->beforeRerun = $callback;

        return $this;
    }

    /**
     * Run diagnostics and approved fixes.
     *
     * @return \Crustum\Audit\Result\DiagnosticReport
     */
    public function run(): DiagnosticReport
    {
        $report = $this->check();

        if (!$this->fix instanceof Closure) {
            return $report;
        }

        $fixes = $this->fixes($report, $this->fix);

        if ($fixes === []) {
            return $report;
        }

        if ($this->beforeRerun instanceof Closure) {
            ($this->beforeRerun)();
        }

        return $this->check()->withFixes($fixes);
    }

    /**
     * Check selected diagnostics.
     *
     * @return \Crustum\Audit\Result\DiagnosticReport
     */
    protected function check(): DiagnosticReport
    {
        $outcomes = [];

        foreach ($this->manager->selectedByGroup($this->selection) as $group => $diagnostics) {
            $groupOutcomes = $this->checkGroup($group, $diagnostics);
            $outcomes = [...$outcomes, ...$groupOutcomes];

            if ($this->bail && $this->hasFailure($groupOutcomes)) {
                break;
            }
        }

        return new DiagnosticReport($outcomes);
    }

    /**
     * Check a diagnostic group.
     *
     * @param string $group Group name.
     * @param list<\Crustum\Audit\Diagnostic> $diagnostics Diagnostics.
     * @return list<\Crustum\Audit\Result\DiagnosticOutcome>
     */
    protected function checkGroup(string $group, array $diagnostics): array
    {
        if (!$this->observer instanceof Closure) {
            return $this->checkDiagnostics($diagnostics);
        }

        $outcomes = null;

        ($this->observer)(
            $group,
            function (?Closure $before = null, ?Closure $after = null) use (&$outcomes, $diagnostics): void {
                $outcomes = $this->checkDiagnostics($diagnostics, $before, $after);
            },
        );

        return $outcomes ?? throw new LogicException(
            'A diagnostic group observer must invoke its continuation.',
        );
    }

    /**
     * Check diagnostic instances.
     *
     * @param list<\Crustum\Audit\Diagnostic> $diagnostics Diagnostics.
     * @param (\Closure(\Crustum\Audit\Diagnostic): void)|null $before Before callback.
     * @param (\Closure(\Crustum\Audit\Result\DiagnosticOutcome): void)|null $after After callback.
     * @return list<\Crustum\Audit\Result\DiagnosticOutcome>
     */
    protected function checkDiagnostics(
        array $diagnostics,
        ?Closure $before = null,
        ?Closure $after = null,
    ): array {
        $outcomes = [];

        foreach ($diagnostics as $diagnostic) {
            if ($before instanceof Closure) {
                $before($diagnostic);
            }

            $outcome = $this->manager->check($diagnostic);
            $outcomes[] = $outcome;

            if ($after instanceof Closure) {
                $after($outcome);
            }

            if ($this->bail && $outcome->result->status->failed()) {
                break;
            }
        }

        return $outcomes;
    }

    /**
     * Determine whether outcomes contain a failure.
     *
     * @param list<\Crustum\Audit\Result\DiagnosticOutcome> $outcomes Outcomes.
     * @return bool
     */
    protected function hasFailure(array $outcomes): bool
    {
        return array_any(
            $outcomes,
            static fn(DiagnosticOutcome $outcome): bool => $outcome->result->status->failed(),
        );
    }

    /**
     * Apply approved fixes.
     *
     * @param \Crustum\Audit\Result\DiagnosticReport $report Diagnostic report.
     * @param \Closure(\Crustum\Audit\Result\DiagnosticOutcome): (bool|string) $fix Fix decision.
     * @return list<\Crustum\Audit\Result\DiagnosticFixOutcome>
     */
    protected function fixes(DiagnosticReport $report, Closure $fix): array
    {
        $fixes = [];

        foreach ($report->diagnostics() as $outcome) {
            if (!$outcome->fixable()) {
                continue;
            }

            $decision = $fix($outcome);

            if ($decision === false) {
                continue;
            }

            $fixes[] = $this->manager->fix(
                $outcome,
                is_string($decision) ? $decision : null,
            );
        }

        return $fixes;
    }
}
