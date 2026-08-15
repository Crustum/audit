<?php
declare(strict_types=1);

namespace Crustum\Audit\Test\Fixtures\Diagnostic;

use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;

/**
 * Database-group diagnostic fixture.
 */
class DatabaseDiagnostic extends Diagnostic
{
    /**
     * @var string
     */
    public string $name = 'Database connects';

    /**
     * @var string
     */
    public string $group = 'database';

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        return DiagnosticResult::pass('The database diagnostic passed.');
    }
}
