<?php
declare(strict_types=1);

namespace Crustum\Audit\Test\Fixtures\Diagnostic;

use Cake\Core\Configure;
use Crustum\Audit\Contract\FixableInterface;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\FixResult;

/**
 * Option-based fixable diagnostic fixture.
 */
class OptionFixableDiagnostic extends Diagnostic implements FixableInterface
{
    /**
     * @var string
     */
    public string $name = 'Testing diagnostic offers fix options';

    /**
     * @var string
     */
    public string $group = 'testing';

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        if (Configure::read('AuditTesting.optionFixed') !== null) {
            return DiagnosticResult::pass('The option diagnostic is fixed.');
        }

        return DiagnosticResult::fail('The option diagnostic failed.')
            ->fixable()
            ->fixOptions(['a' => 'Option A', 'b' => 'Option B']);
    }

    /**
     * @inheritDoc
     */
    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        Configure::write('AuditTesting.optionFixed', $option);

        return FixResult::pass(sprintf('The option diagnostic was fixed with [%s].', $option));
    }
}
