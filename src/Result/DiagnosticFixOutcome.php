<?php
declare(strict_types=1);

namespace Crustum\Audit\Result;

use Crustum\Audit\Diagnostic;
use Crustum\Audit\DiagnosticSource;
use JsonSerializable;

/**
 * A diagnostic paired with a fix result.
 */
class DiagnosticFixOutcome implements JsonSerializable
{
    /**
     * Constructor.
     *
     * @param \Crustum\Audit\Diagnostic $diagnostic Diagnostic instance.
     * @param \Crustum\Audit\Result\FixResult $result Fix result.
     */
    public function __construct(
        public Diagnostic $diagnostic,
        public FixResult $result,
    ) {
    }

    /**
     * Get diagnostic source metadata.
     *
     * @return \Crustum\Audit\DiagnosticSource
     */
    public function source(): DiagnosticSource
    {
        return DiagnosticSource::for($this->diagnostic);
    }

    /**
     * Get the array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $source = $this->source();

        return [
            'class' => $this->diagnostic::class,
            'group' => $this->diagnostic->group,
            'name' => $this->diagnostic->name,
            'source' => [
                'label' => $source->label(),
                'package' => $source->package,
                'file' => $source->relativeFile,
                'application' => $source->application,
            ],
            'code' => $this->result->code,
            'status' => $this->result->status->value,
            'summary' => $this->result->summary,
            'details' => $this->result->details,
        ];
    }

    /**
     * Get the JSON representation.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
