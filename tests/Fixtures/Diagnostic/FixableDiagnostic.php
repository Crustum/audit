<?php
declare(strict_types=1);

namespace Crustum\Audit\Test\Fixtures\Diagnostic;

use Crustum\Audit\Contract\FixableInterface;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\FixResult;

/**
 * Fixable diagnostic fixture.
 */
class FixableDiagnostic extends Diagnostic implements FixableInterface
{
    /**
     * @var string
     */
    public string $name = 'Testing diagnostic is fixable';

    /**
     * @var string
     */
    public string $group = 'testing';

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        return DiagnosticResult::fail('The diagnostic failed.')
            ->fixable()
            ->confirmUsing('Fix the testing diagnostic?')
            ->suggest('Apply the testing diagnostic fix.');
    }

    /**
     * @inheritDoc
     */
    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        return FixResult::pass('The diagnostic was fixed.');
    }
}
