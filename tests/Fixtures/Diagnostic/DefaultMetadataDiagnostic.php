<?php
declare(strict_types=1);

namespace Crustum\Audit\Test\Fixtures\Diagnostic;

use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;

/**
 * Diagnostic fixture using generated metadata.
 */
class DefaultMetadataDiagnostic extends Diagnostic
{
    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        return DiagnosticResult::pass();
    }
}
