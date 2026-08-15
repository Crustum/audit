<?php
declare(strict_types=1);

namespace Crustum\Audit\Result;

/**
 * Result returned after applying a diagnostic fix.
 *
 * @phpstan-consistent-constructor
 */
class FixResult
{
    /**
     * Detailed evidence.
     *
     * @var string|null
     */
    public ?string $details = null;

    /**
     * Structured context.
     *
     * @var array<string, mixed>
     */
    public array $context = [];

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
    public static function pass(string $summary = 'Fixed', ?string $code = null): static
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
