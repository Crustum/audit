<?php
declare(strict_types=1);

namespace Crustum\Audit\Result;

use Crustum\Audit\EnvironmentMode;

/**
 * Result returned by a diagnostic check.
 *
 * @phpstan-consistent-constructor
 */
class DiagnosticResult
{
    /**
     * Whether this result represents an outcome the diagnostic can fix.
     *
     * @var bool
     */
    public bool $fixable = false;

    /**
     * Environment modes in which the fix may be applied.
     *
     * @var list<\Crustum\Audit\EnvironmentMode>
     */
    public array $fixableEnvironments = [];

    /**
     * Detailed evidence.
     *
     * @var string|null
     */
    public ?string $details = null;

    /**
     * Remediation guidance.
     *
     * @var string|null
     */
    public ?string $remediation = null;

    /**
     * Related links.
     *
     * @var list<\Crustum\Audit\Result\Link>
     */
    public array $links = [];

    /**
     * Structured context.
     *
     * @var array<string, mixed>
     */
    public array $context = [];

    /**
     * Fix confirmation prompt.
     *
     * @var string|null
     */
    public ?string $confirmation = null;

    /**
     * Fix choices keyed by option value.
     *
     * @var array<string, string>
     */
    public array $fixOptions = [];

    /**
     * Label for declining an offered fix.
     *
     * @var string|null
     */
    public ?string $fixDeclineLabel = null;

    /**
     * Constructor.
     *
     * @param \Crustum\Audit\Result\Status $status Result status.
     * @param string $summary Result summary.
     * @param string|null $code Stable machine-readable code.
     */
    public function __construct(
        public Status $status,
        public string $summary,
        public ?string $code = null,
    ) {
    }

    /**
     * Create a passing result.
     *
     * @param string $summary Result summary.
     * @param string|null $code Stable machine-readable code.
     * @return static
     */
    public static function pass(string $summary = 'Passed', ?string $code = null): static
    {
        return new static(Status::Pass, $summary, $code);
    }

    /**
     * Create a warning result.
     *
     * @param string $summary Result summary.
     * @param string|null $code Stable machine-readable code.
     * @return static
     */
    public static function warn(string $summary, ?string $code = null): static
    {
        return new static(Status::Warn, $summary, $code);
    }

    /**
     * Create a notice result.
     *
     * @param string $summary Result summary.
     * @param string|null $code Stable machine-readable code.
     * @return static
     */
    public static function notice(string $summary, ?string $code = null): static
    {
        return new static(Status::Notice, $summary, $code);
    }

    /**
     * Create a failing result.
     *
     * @param string $summary Result summary.
     * @param string|null $code Stable machine-readable code.
     * @return static
     */
    public static function fail(string $summary, ?string $code = null): static
    {
        return new static(Status::Fail, $summary, $code);
    }

    /**
     * Create a skipped result.
     *
     * @param string $summary Result summary.
     * @param string|null $code Stable machine-readable code.
     * @return static
     */
    public static function skip(string $summary, ?string $code = null): static
    {
        return new static(Status::Skip, $summary, $code);
    }

    /**
     * Create an error result.
     *
     * @param string $summary Result summary.
     * @param string|null $code Stable machine-readable code.
     * @return static
     */
    public static function error(string $summary, ?string $code = null): static
    {
        return new static(Status::Error, $summary, $code);
    }

    /**
     * Add detailed evidence.
     *
     * @param string $details Detailed evidence.
     * @return static
     */
    public function withDetails(string $details): static
    {
        $this->details = $details;

        return $this;
    }

    /**
     * Add remediation guidance.
     *
     * @param string $message Remediation guidance.
     * @return static
     */
    public function suggest(string $message): static
    {
        $this->remediation = $message;

        return $this;
    }

    /**
     * Set the fix confirmation prompt.
     *
     * @param string $prompt Confirmation prompt.
     * @return static
     */
    public function confirmUsing(string $prompt): static
    {
        $this->confirmation = $prompt;

        return $this;
    }

    /**
     * Mark the result as eligible for a fix.
     *
     * @param \Crustum\Audit\EnvironmentMode ...$environments Allowed modes.
     * @return static
     */
    public function fixable(EnvironmentMode ...$environments): static
    {
        $this->fixable = true;
        $this->fixableEnvironments = array_values($environments);

        return $this;
    }

    /**
     * Offer a choice of fixes.
     *
     * @param array<string, string> $options Labels keyed by option value.
     * @param string|null $decline Label for declining the fix.
     * @return static
     */
    public function fixOptions(array $options, ?string $decline = null): static
    {
        $this->fixOptions = $options;
        $this->fixDeclineLabel = $decline;

        return $this;
    }

    /**
     * Determine whether the fix may be applied in a mode.
     *
     * @param \Crustum\Audit\EnvironmentMode $environment Current mode.
     * @return bool
     */
    public function fixableIn(EnvironmentMode $environment): bool
    {
        return $this->fixable
            && ($this->fixableEnvironments === []
                || in_array($environment, $this->fixableEnvironments, true));
    }

    /**
     * Add a related link.
     *
     * @param \Crustum\Audit\Result\Link $link Related link.
     * @return static
     */
    public function link(Link $link): static
    {
        $this->links[] = $link;

        return $this;
    }

    /**
     * Add structured context.
     *
     * @param string $key Context key.
     * @param mixed $value Context value.
     * @return static
     */
    public function withContext(string $key, mixed $value): static
    {
        $this->context[$key] = $value;

        return $this;
    }
}
