<?php
declare(strict_types=1);

namespace Crustum\Audit\Test\Fixtures\Diagnostic;

use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use RuntimeException;

/**
 * Throwing diagnostic fixture.
 */
class ThrowingDiagnostic extends Diagnostic
{
    /**
     * @var string
     */
    public string $name = 'Testing diagnostic throws';

    /**
     * @var string
     */
    public string $group = 'testing';

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        throw new RuntimeException('The diagnostic exploded.');
    }
}
