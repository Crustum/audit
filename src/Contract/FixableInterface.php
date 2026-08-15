<?php
declare(strict_types=1);

namespace Crustum\Audit\Contract;

use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\FixResult;

/**
 * Contract for diagnostics that can repair a failed result.
 */
interface FixableInterface
{
    /**
     * Apply a fix to a diagnostic result.
     *
     * @param \Crustum\Audit\Result\DiagnosticResult $result Failed result.
     * @param string|null $option Selected option when the result offers choices.
     * @return \Crustum\Audit\Result\FixResult
     */
    public function fix(DiagnosticResult $result, ?string $option = null): FixResult;
}
