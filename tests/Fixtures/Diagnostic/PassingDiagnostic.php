<?php
declare(strict_types=1);

namespace Crustum\Audit\Test\Fixtures\Diagnostic;

use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;

/**
 * Passing diagnostic fixture.
 */
class PassingDiagnostic extends Diagnostic
{
    /**
     * @var string
     */
    public string $name = 'Testing diagnostic passes';

    /**
     * @var string
     */
    public string $group = 'testing';

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        return DiagnosticResult::pass('The diagnostic passed.');
    }
}
