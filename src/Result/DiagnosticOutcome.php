<?php
declare(strict_types=1);

namespace Crustum\Audit\Result;

use Crustum\Audit\Contract\FixableInterface;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\DiagnosticSource;
use Crustum\Audit\EnvironmentMode;
use JsonSerializable;

/**
 * A diagnostic paired with its result.
 */
class DiagnosticOutcome implements JsonSerializable
{
    /**
     * Constructor.
     *
     * @param \Crustum\Audit\Diagnostic $diagnostic Diagnostic instance.
     * @param \Crustum\Audit\Result\DiagnosticResult $result Diagnostic result.
     */
    public function __construct(
        public Diagnostic $diagnostic,
        public DiagnosticResult $result,
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
     * Determine whether this outcome may be fixed.
     *
     * @return bool
     */
    public function fixable(): bool
    {
        return $this->diagnostic instanceof FixableInterface
            && $this->result->status === Status::Fail
            && $this->result->fixableIn(EnvironmentMode::current());
    }

    /**
     * Determine whether the fix requires an option.
     *
     * @return bool
     */
    public function fixRequiresOption(): bool
    {
        return $this->fixable() && $this->result->fixOptions !== [];
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
            'fixable' => $this->fixable(),
            'options' => $this->result->fixOptions,
            'summary' => $this->result->summary,
            'details' => $this->result->details,
            'remediation' => $this->result->remediation,
            'links' => $this->links(),
        ];
    }

    /**
     * Get the JSON representation.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [...$this->toArray(), 'links' => (object)$this->links(agent: true)];
    }

    /**
     * Get result links keyed by label.
     *
     * @param bool $agent Whether to use agent URLs.
     * @return array<string, string>
     */
    protected function links(bool $agent = false): array
    {
        return array_column(
            $this->result->links,
            $agent ? 'agentUrl' : 'url',
            'label',
        );
    }
}
