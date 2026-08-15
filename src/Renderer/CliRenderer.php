<?php
declare(strict_types=1);

namespace Crustum\Audit\Renderer;

use Cake\Console\ConsoleIo;
use Cake\Console\Helper;
use Crustum\Audit\Result\DiagnosticFixOutcome;
use Crustum\Audit\Result\DiagnosticOutcome;
use Crustum\Audit\Result\DiagnosticReport;
use Crustum\Audit\Result\Link;
use Crustum\Audit\Result\Status;

/**
 * Renders diagnostic reports for interactive CLI output via Crustum Prompts helpers.
 */
class CliRenderer
{
    /**
     * Constructor.
     *
     * @param \Cake\Console\ConsoleIo $io Console IO.
     */
    public function __construct(protected ConsoleIo $io)
    {
    }

    /**
     * Render the diagnostic report.
     *
     * @param \Crustum\Audit\Result\DiagnosticReport $report Diagnostic report.
     * @return void
     */
    public function render(DiagnosticReport $report): void
    {
        $this->helper('Intro')->output(['message' => 'Results']);

        $verbose = $this->io->level() >= ConsoleIo::VERBOSE;
        $notices = [];
        $failedFixes = $this->failedFixesByDiagnostic($report->fixes());
        $mergedFixes = [];

        foreach ($report->diagnostics() as $outcome) {
            if ($outcome->result->status === Status::Notice) {
                $notices[] = $outcome;

                continue;
            }

            if ($this->isReportableIssue($outcome)) {
                $failedFix = $failedFixes[$outcome->diagnostic::class] ?? null;

                if ($failedFix !== null) {
                    $mergedFixes[] = $failedFix;
                }

                $this->renderIssue($outcome, $failedFix);

                continue;
            }

            if (!$verbose && in_array($outcome->result->status, [Status::Pass, Status::Skip], true)) {
                continue;
            }

            $this->io->out(sprintf(
                '[%s] %s (%s): %s',
                $outcome->result->status->value,
                $outcome->diagnostic->name,
                $outcome->source()->label(),
                $outcome->result->summary,
            ));

            $this->renderDiagnosticDetails($outcome);
        }

        $this->renderNotices($notices);
        $this->renderFixes(array_values(array_filter(
            $report->fixes(),
            static fn(DiagnosticFixOutcome $fix): bool => !in_array($fix, $mergedFixes, true),
        )));

        if ($report->hasFailures()) {
            $this->helper('Error')->output(['message' => 'Audit found failing diagnostics.']);

            return;
        }

        if ($report->hasWarnings()) {
            $this->helper('Warning')->output(['message' => 'Audit found warnings.']);

            return;
        }

        if ($report->fixes() !== []) {
            $this->helper('Info')->output(['message' => 'All diagnostics passed or were fixed.']);

            return;
        }

        $this->helper('Info')->output(['message' => 'All diagnostics passed.']);
    }

    /**
     * Render a diagnostic fix confirmation as a callout.
     *
     * @param \Crustum\Audit\Result\DiagnosticOutcome $outcome Diagnostic outcome.
     * @return void
     */
    public function renderFixConfirmation(DiagnosticOutcome $outcome): void
    {
        $this->helper('Callout')->output([
            'label' => $outcome->diagnostic->name,
            'content' => $this->fixConfirmationCalloutContent($outcome),
            'type' => $outcome->result->status === Status::Warn ? 'warning' : 'error',
            'info' => $outcome->source()->label(),
        ]);
    }

    /**
     * Determine whether the diagnostic should be displayed as an issue.
     *
     * @param \Crustum\Audit\Result\DiagnosticOutcome $outcome Diagnostic outcome.
     * @return bool
     */
    protected function isReportableIssue(DiagnosticOutcome $outcome): bool
    {
        return in_array($outcome->result->status, [Status::Warn, Status::Fail, Status::Error], true);
    }

    /**
     * Render an issue diagnostic as a callout.
     *
     * @param \Crustum\Audit\Result\DiagnosticOutcome $outcome Diagnostic outcome.
     * @param \Crustum\Audit\Result\DiagnosticFixOutcome|null $failedFix Failed fix outcome.
     * @return void
     */
    protected function renderIssue(DiagnosticOutcome $outcome, ?DiagnosticFixOutcome $failedFix = null): void
    {
        $this->helper('Callout')->output([
            'label' => $outcome->diagnostic->name,
            'content' => $this->diagnosticCalloutContent($outcome, failedFix: $failedFix),
            'type' => $this->issueCalloutType($outcome, $failedFix),
            'info' => $outcome->source()->label(),
        ]);
    }

    /**
     * Get the callout type for an issue, escalating when a fix failed.
     *
     * @param \Crustum\Audit\Result\DiagnosticOutcome $outcome Diagnostic outcome.
     * @param \Crustum\Audit\Result\DiagnosticFixOutcome|null $failedFix Failed fix outcome.
     * @return string
     */
    protected function issueCalloutType(DiagnosticOutcome $outcome, ?DiagnosticFixOutcome $failedFix): string
    {
        if ($failedFix instanceof DiagnosticFixOutcome) {
            return 'error';
        }

        return $outcome->result->status === Status::Warn ? 'warning' : 'error';
    }

    /**
     * Key the failed fixes by the diagnostic that attempted them.
     *
     * @param list<\Crustum\Audit\Result\DiagnosticFixOutcome> $fixes Fix outcomes.
     * @return array<class-string, \Crustum\Audit\Result\DiagnosticFixOutcome>
     */
    protected function failedFixesByDiagnostic(array $fixes): array
    {
        $failed = [];

        foreach ($fixes as $fix) {
            if ($fix->result->status->failed()) {
                $failed[$fix->diagnostic::class] = $fix;
            }
        }

        return $failed;
    }

    /**
     * Render diagnostic details, remediation, and links.
     *
     * @param \Crustum\Audit\Result\DiagnosticOutcome $outcome Diagnostic outcome.
     * @return void
     */
    protected function renderDiagnosticDetails(DiagnosticOutcome $outcome): void
    {
        if ($outcome->result->details !== null) {
            $this->io->out('    ' . $outcome->result->details);
        }

        if ($outcome->result->remediation !== null) {
            $this->io->out('    ' . $outcome->result->remediation);
        }

        foreach ($outcome->result->links as $link) {
            $this->io->out('    ' . $link->url);
        }
    }

    /**
     * Render notice diagnostics as callouts grouped by source.
     *
     * @param list<\Crustum\Audit\Result\DiagnosticOutcome> $notices Notice outcomes.
     * @return void
     */
    protected function renderNotices(array $notices): void
    {
        if ($notices === []) {
            return;
        }

        foreach ($this->noticesBySource($notices) as $source => $sourceNotices) {
            $this->helper('Callout')->output([
                'label' => count($sourceNotices) === 1 ? 'Notice' : 'Notices',
                'content' => $this->noticesCalloutContent($sourceNotices),
                'info' => $source,
            ]);
        }
    }

    /**
     * @param list<\Crustum\Audit\Result\DiagnosticOutcome> $notices Notice outcomes.
     * @return array<string, list<\Crustum\Audit\Result\DiagnosticOutcome>>
     */
    protected function noticesBySource(array $notices): array
    {
        $grouped = [];

        foreach ($notices as $notice) {
            $grouped[$notice->source()->label()][] = $notice;
        }

        return $grouped;
    }

    /**
     * Format notice content for a callout.
     *
     * @param list<\Crustum\Audit\Result\DiagnosticOutcome> $notices Notice outcomes.
     * @return list<string>
     */
    protected function noticesCalloutContent(array $notices): array
    {
        $content = [];

        foreach ($notices as $notice) {
            $content[] = $this->noticeItem($notice);

            foreach ($notice->result->links as $link) {
                $content[] = $this->linkLabel($link);
            }
        }

        return $content;
    }

    /**
     * Format a notice as a compact list item.
     *
     * @param \Crustum\Audit\Result\DiagnosticOutcome $outcome Diagnostic outcome.
     * @return string
     */
    protected function noticeItem(DiagnosticOutcome $outcome): string
    {
        $parts = [$outcome->result->summary];

        if ($outcome->result->details !== null) {
            $parts[] = $outcome->result->details;
        }

        if ($outcome->result->remediation !== null) {
            $parts[] = $outcome->result->remediation;
        }

        return $this->squish(implode(' ', $parts));
    }

    /**
     * Render diagnostic fixes as callouts grouped by source.
     *
     * @param list<\Crustum\Audit\Result\DiagnosticFixOutcome> $fixes Fix outcomes.
     * @return void
     */
    protected function renderFixes(array $fixes): void
    {
        if ($fixes === []) {
            return;
        }

        foreach ($this->fixesBySource($fixes) as $source => $sourceFixes) {
            $this->helper('Callout')->output([
                'label' => count($sourceFixes) === 1 ? $sourceFixes[0]->diagnostic->name : 'Fixes',
                'content' => $this->fixesCalloutContent($sourceFixes),
                'type' => $this->fixesCalloutType($sourceFixes),
                'info' => $source,
            ]);
        }
    }

    /**
     * @param list<\Crustum\Audit\Result\DiagnosticFixOutcome> $fixes Fix outcomes.
     * @return array<string, list<\Crustum\Audit\Result\DiagnosticFixOutcome>>
     */
    protected function fixesBySource(array $fixes): array
    {
        $grouped = [];

        foreach ($fixes as $fix) {
            $grouped[$fix->source()->label()][] = $fix;
        }

        return $grouped;
    }

    /**
     * Format fix content for a callout.
     *
     * @param list<\Crustum\Audit\Result\DiagnosticFixOutcome> $fixes Fix outcomes.
     * @return list<string>
     */
    protected function fixesCalloutContent(array $fixes): array
    {
        if (count($fixes) === 1) {
            return [$this->fixSummary($fixes[0])];
        }

        return array_map(
            fn(DiagnosticFixOutcome $fix): string => '- ' . $this->fixItem($fix),
            $fixes,
        );
    }

    /**
     * Format a fix outcome as a compact list item.
     *
     * @param \Crustum\Audit\Result\DiagnosticFixOutcome $outcome Fix outcome.
     * @return string
     */
    protected function fixItem(DiagnosticFixOutcome $outcome): string
    {
        return sprintf('%s: %s', $outcome->diagnostic->name, $this->fixSummary($outcome));
    }

    /**
     * Format a fix outcome's summary and details as a single line.
     *
     * @param \Crustum\Audit\Result\DiagnosticFixOutcome $outcome Fix outcome.
     * @return string
     */
    protected function fixSummary(DiagnosticFixOutcome $outcome): string
    {
        $parts = [$outcome->result->summary];

        if ($outcome->result->details !== null) {
            $parts[] = $outcome->result->details;
        }

        return $this->squish(implode(' ', $parts));
    }

    /**
     * @param list<\Crustum\Audit\Result\DiagnosticFixOutcome> $fixes Fix outcomes.
     * @return string|null
     */
    protected function fixesCalloutType(array $fixes): ?string
    {
        foreach ($fixes as $fix) {
            if ($fix->result->status->failed()) {
                return 'error';
            }
        }

        foreach ($fixes as $fix) {
            if ($fix->result->status === Status::Warn) {
                return 'warning';
            }
        }

        return null;
    }

    /**
     * Format diagnostic content for a callout.
     *
     * @param \Crustum\Audit\Result\DiagnosticOutcome $outcome Diagnostic outcome.
     * @param bool $includeRemediation Whether to include remediation text.
     * @param \Crustum\Audit\Result\DiagnosticFixOutcome|null $failedFix Failed fix outcome.
     * @return list<string>
     */
    protected function diagnosticCalloutContent(
        DiagnosticOutcome $outcome,
        bool $includeRemediation = true,
        ?DiagnosticFixOutcome $failedFix = null,
    ): array {
        $content = [$outcome->result->summary];

        if ($outcome->result->details !== null) {
            $content[] = 'Details';
            $content[] = $outcome->result->details;
        }

        if ($failedFix instanceof DiagnosticFixOutcome) {
            $content[] = 'Fix failed';
            $content[] = $this->fixSummary($failedFix);
        }

        if ($includeRemediation && !$failedFix instanceof DiagnosticFixOutcome && $outcome->result->remediation !== null) {
            $content[] = 'Suggested fix';
            $content[] = $outcome->result->remediation;
        }

        foreach ($outcome->result->links as $link) {
            $content[] = $this->linkLabel($link);
        }

        return $content;
    }

    /**
     * Format fix confirmation content for a callout.
     *
     * @param \Crustum\Audit\Result\DiagnosticOutcome $outcome Diagnostic outcome.
     * @return list<string>
     */
    protected function fixConfirmationCalloutContent(DiagnosticOutcome $outcome): array
    {
        return $this->diagnosticCalloutContent($outcome, includeRemediation: false);
    }

    /**
     * Format a related link for callout content.
     *
     * @param \Crustum\Audit\Result\Link $link Related link.
     * @return string
     */
    protected function linkLabel(Link $link): string
    {
        return explode('#', (string)preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $link->url), 2)[0];
    }

    /**
     * Collapse whitespace in a display string.
     *
     * @param string $value Raw value.
     * @return string
     */
    protected function squish(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
    }

    /**
     * Load a Crustum Prompts console helper.
     *
     * @param string $name Helper short name.
     * @return \Cake\Console\Helper
     */
    protected function helper(string $name): Helper
    {
        return $this->io->helper('Crustum/Prompts.' . $name);
    }
}
