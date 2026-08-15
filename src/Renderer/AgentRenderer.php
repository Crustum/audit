<?php
declare(strict_types=1);

namespace Crustum\Audit\Renderer;

use Crustum\Audit\Result\DiagnosticFixOutcome;
use Crustum\Audit\Result\DiagnosticOutcome;
use Crustum\Audit\Result\DiagnosticReport;
use Crustum\Audit\Result\Status;

/**
 * Renders diagnostic reports as agent-optimized JSON.
 */
class AgentRenderer
{
    /**
     * Render the report as a single line of agent-optimized JSON.
     *
     * @param \Crustum\Audit\Result\DiagnosticReport $report Diagnostic report.
     * @return string
     */
    public function render(DiagnosticReport $report): string
    {
        return json_encode($this->payload($report), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Build the agent-optimized report payload.
     *
     * @param \Crustum\Audit\Result\DiagnosticReport $report Diagnostic report.
     * @return array<string, mixed>
     */
    protected function payload(DiagnosticReport $report): array
    {
        $counts = array_fill_keys(array_column(Status::cases(), 'value'), 0);
        $issues = [];

        foreach ($report->diagnostics() as $outcome) {
            $counts[$outcome->result->status->value]++;

            if ($outcome->result->status !== Status::Pass && $outcome->result->status !== Status::Skip) {
                $issues[] = $this->issue($outcome);
            }
        }

        return array_filter([
            'tool' => 'audit',
            'result' => $report->hasFailures() ? 'failed' : 'passed',
            'diagnostics' => array_sum($counts),
            'failed' => $counts[Status::Fail->value] + $counts[Status::Error->value],
            'warnings' => $counts[Status::Warn->value],
            'notices' => $counts[Status::Notice->value],
            'passed' => $counts[Status::Pass->value],
            'skipped' => $counts[Status::Skip->value],
            'issues' => $issues,
            'fixes' => array_map($this->fix(...), $report->fixes()),
        ], static fn(mixed $value): bool => $value !== []);
    }

    /**
     * Build the payload entry for an actionable diagnostic outcome.
     *
     * @param \Crustum\Audit\Result\DiagnosticOutcome $outcome Diagnostic outcome.
     * @return array<string, mixed>
     */
    protected function issue(DiagnosticOutcome $outcome): array
    {
        return array_filter([
            'name' => $outcome->diagnostic->name,
            'status' => $outcome->result->status->value,
            'summary' => $outcome->result->summary,
            'details' => $outcome->result->details,
            'fix' => $outcome->result->remediation,
            'fixable' => $outcome->fixable() && !$outcome->fixRequiresOption() ?: null,
            'options' => $outcome->result->fixOptions,
            'links' => array_column($outcome->result->links, 'agentUrl', 'label'),
        ], static fn(mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * Build the payload entry for an applied fix.
     *
     * @param \Crustum\Audit\Result\DiagnosticFixOutcome $outcome Fix outcome.
     * @return array<string, mixed>
     */
    protected function fix(DiagnosticFixOutcome $outcome): array
    {
        return array_filter([
            'name' => $outcome->diagnostic->name,
            'status' => $outcome->result->status->value,
            'summary' => $outcome->result->summary,
            'details' => $outcome->result->details,
        ], static fn(mixed $value): bool => $value !== null);
    }
}
