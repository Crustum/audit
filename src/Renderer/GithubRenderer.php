<?php
declare(strict_types=1);

namespace Crustum\Audit\Renderer;

use Crustum\Audit\Result\DiagnosticOutcome;
use Crustum\Audit\Result\DiagnosticReport;
use Crustum\Audit\Result\Status;

/**
 * Renders diagnostic reports as GitHub Actions annotations.
 */
class GithubRenderer
{
    /**
     * Render the report as GitHub Actions workflow annotations.
     *
     * @param \Crustum\Audit\Result\DiagnosticReport $report Diagnostic report.
     * @return list<string>
     */
    public function annotations(DiagnosticReport $report): array
    {
        $annotations = [];

        foreach ($report->diagnostics() as $outcome) {
            if ($outcome->result->status === Status::Pass) {
                continue;
            }

            if ($outcome->result->status === Status::Skip) {
                continue;
            }

            $annotations[] = sprintf(
                '::%s title=%s::%s',
                $this->level($outcome),
                $this->escapeProperty($this->title($outcome)),
                $this->escapeData($this->summary($outcome)),
            );
        }

        return $annotations;
    }

    /**
     * Get the annotation level for a diagnostic outcome.
     *
     * @param \Crustum\Audit\Result\DiagnosticOutcome $outcome Diagnostic outcome.
     * @return string
     */
    protected function level(DiagnosticOutcome $outcome): string
    {
        return match ($outcome->result->status) {
            Status::Notice => 'notice',
            Status::Warn => 'warning',
            default => 'error',
        };
    }

    /**
     * Build an annotation title.
     *
     * @param \Crustum\Audit\Result\DiagnosticOutcome $outcome Diagnostic outcome.
     * @return string
     */
    protected function title(DiagnosticOutcome $outcome): string
    {
        return sprintf('%s (%s)', $outcome->diagnostic->name, $outcome->source()->label());
    }

    /**
     * Build an annotation summary.
     *
     * @param \Crustum\Audit\Result\DiagnosticOutcome $outcome Diagnostic outcome.
     * @return string
     */
    protected function summary(DiagnosticOutcome $outcome): string
    {
        $parts = [$outcome->result->summary];

        if ($outcome->result->details !== null) {
            $parts[] = $outcome->result->details;
        }

        if ($outcome->result->remediation !== null) {
            $parts[] = $outcome->result->remediation;
        }

        foreach ($outcome->result->links as $link) {
            $parts[] = $link->url;
        }

        return implode(' ', $parts);
    }

    /**
     * Escape a GitHub workflow command property value.
     *
     * @param string $value Property value.
     * @return string
     */
    protected function escapeProperty(string $value): string
    {
        return str_replace(
            ['%', "\r", "\n", ':', ','],
            ['%25', '%0D', '%0A', '%3A', '%2C'],
            $value,
        );
    }

    /**
     * Escape a GitHub workflow command message body.
     *
     * @param string $value Message body.
     * @return string
     */
    protected function escapeData(string $value): string
    {
        return str_replace(
            ['%', "\r", "\n"],
            ['%25', '%0D', '%0A'],
            $value,
        );
    }
}
