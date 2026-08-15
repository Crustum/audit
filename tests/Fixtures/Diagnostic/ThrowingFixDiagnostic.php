<?php
declare(strict_types=1);

namespace Crustum\Audit\Test\Fixtures\Diagnostic;

use Crustum\Audit\Contract\FixableInterface;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\FixResult;
use RuntimeException;

/**
 * Throwing fix fixture.
 */
class ThrowingFixDiagnostic extends Diagnostic implements FixableInterface
{
    /**
     * @var string
     */
    public string $name = 'Testing diagnostic fix throws';

    /**
     * @var string
     */
    public string $group = 'testing';

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        return DiagnosticResult::fail('The diagnostic failed.')->fixable();
    }

    /**
     * @inheritDoc
     */
    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        throw new RuntimeException('permission denied');
    }
}
