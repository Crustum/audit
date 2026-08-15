<?php
declare(strict_types=1);

namespace Crustum\Audit\Result;

use JsonSerializable;

/**
 * Aggregated diagnostic and fix outcomes.
 */
class DiagnosticReport implements JsonSerializable
{
    /**
     * Machine-readable report version.
     *
     * @var int
     */
    public const VERSION = 1;

    /**
     * Constructor.
     *
     * @param list<\Crustum\Audit\Result\DiagnosticOutcome> $diagnostics Diagnostic outcomes.
     * @param list<\Crustum\Audit\Result\DiagnosticFixOutcome> $fixes Fix outcomes.
     */
    public function __construct(
        protected array $diagnostics = [],
        protected array $fixes = [],
    ) {
    }

    /**
     * Get diagnostic outcomes.
     *
     * @return list<\Crustum\Audit\Result\DiagnosticOutcome>
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * Get fix outcomes.
     *
     * @return list<\Crustum\Audit\Result\DiagnosticFixOutcome>
     */
    public function fixes(): array
    {
        return $this->fixes;
    }

    /**
     * Return a report with additional fix outcomes.
     *
     * @param list<\Crustum\Audit\Result\DiagnosticFixOutcome> $fixes Fix outcomes.
     * @return self
     */
    public function withFixes(array $fixes): self
    {
        return new self($this->diagnostics, [...$this->fixes, ...$fixes]);
    }

    /**
     * Determine whether any outcome failed.
     *
     * @return bool
     */
    public function hasFailures(): bool
    {
        return array_any(
            $this->diagnostics,
            static fn(DiagnosticOutcome $outcome): bool => $outcome->result->status->failed(),
        ) || array_any(
            $this->fixes,
            static fn(DiagnosticFixOutcome $outcome): bool => $outcome->result->status->failed(),
        );
    }

    /**
     * Determine whether any outcome warned.
     *
     * @return bool
     */
    public function hasWarnings(): bool
    {
        return array_any(
            $this->diagnostics,
            static fn(DiagnosticOutcome $outcome): bool => $outcome->result->status === Status::Warn,
        ) || array_any(
            $this->fixes,
            static fn(DiagnosticFixOutcome $outcome): bool => $outcome->result->status === Status::Warn,
        );
    }

    /**
     * Get the array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'diagnostics' => array_map(
                static fn(DiagnosticOutcome $outcome): array => $outcome->toArray(),
                $this->diagnostics,
            ),
            'fixes' => array_map(
                static fn(DiagnosticFixOutcome $outcome): array => $outcome->toArray(),
                $this->fixes,
            ),
        ];
    }

    /**
     * Get the JSON representation.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'version' => self::VERSION,
            'diagnostics' => $this->diagnostics,
            'fixes' => $this->fixes,
        ];
    }
}
