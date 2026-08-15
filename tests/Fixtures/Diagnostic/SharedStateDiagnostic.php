<?php
declare(strict_types=1);

namespace Crustum\Audit\Test\Fixtures\Diagnostic;

use Cake\Core\Configure;
use Crustum\Audit\Contract\FixableInterface;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\FixResult;

/**
 * Shared-state fix fixture.
 */
class SharedStateDiagnostic extends Diagnostic implements FixableInterface
{
    /**
     * @var string
     */
    public string $name = 'Testing diagnostic fixes shared state';

    /**
     * @var string
     */
    public string $group = 'testing';

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        if (Configure::read('AuditTesting.sharedStateFixed') === true) {
            return DiagnosticResult::pass('The shared state is fixed.');
        }

        return DiagnosticResult::fail('The shared state needs fixing.')->fixable();
    }

    /**
     * @inheritDoc
     */
    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        Configure::write('AuditTesting.sharedStateFixed', true);

        return FixResult::pass('The shared state fix ran.');
    }
}
