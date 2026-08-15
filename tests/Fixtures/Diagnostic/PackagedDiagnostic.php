<?php
declare(strict_types=1);

namespace Crustum\Audit\Test\Fixtures\Diagnostic;

use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Override;

/**
 * Package-owned diagnostic fixture.
 */
class PackagedDiagnostic extends Diagnostic
{
    /**
     * @var string
     */
    public string $name = 'Testing diagnostic from a package';

    /**
     * @var string
     */
    public string $group = 'testing';

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        return DiagnosticResult::pass('The packaged diagnostic passed.');
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function package(): ?string
    {
        return 'vendor/package';
    }
}
