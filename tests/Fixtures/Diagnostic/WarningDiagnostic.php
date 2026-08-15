<?php
declare(strict_types=1);

namespace Crustum\Audit\Test\Fixtures\Diagnostic;

use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;

/**
 * Warning diagnostic fixture.
 */
class WarningDiagnostic extends Diagnostic
{
    /**
     * @var string
     */
    public string $name = 'Testing diagnostic warns';

    /**
     * @var string
     */
    public string $group = 'testing';

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        return DiagnosticResult::warn('The diagnostic warned.')
            ->suggest('Re-run this diagnostic after addressing the warning.');
    }
}
