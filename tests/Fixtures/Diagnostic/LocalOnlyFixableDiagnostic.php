<?php
declare(strict_types=1);

namespace Crustum\Audit\Test\Fixtures\Diagnostic;

use Crustum\Audit\Contract\FixableInterface;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\EnvironmentMode;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\FixResult;

/**
 * Local-only fixable diagnostic fixture.
 */
class LocalOnlyFixableDiagnostic extends Diagnostic implements FixableInterface
{
    /**
     * @var string
     */
    public string $name = 'Testing diagnostic is fixable locally';

    /**
     * @var string
     */
    public string $group = 'testing';

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        return DiagnosticResult::fail('The local diagnostic failed.')
            ->fixable(EnvironmentMode::Local);
    }

    /**
     * @inheritDoc
     */
    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        return FixResult::pass('The local diagnostic was fixed.');
    }
}
