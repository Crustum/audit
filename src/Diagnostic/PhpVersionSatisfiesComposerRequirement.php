<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Composer\Semver\Semver;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\Link;
use Crustum\Audit\Result\Message;
use Crustum\Audit\Support\ComposerJson;
use Override;

/**
 * Verifies the running PHP version against composer.json.
 */
final class PhpVersionSatisfiesComposerRequirement extends Diagnostic
{
    public string $name = 'PHP satisfies Composer';

    public string $group = 'environment';

    /**
     * Constructor.
     *
     * @param \Crustum\Audit\Support\ComposerJson $composer Composer metadata.
     */
    public function __construct(private readonly ComposerJson $composer)
    {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function messages(): array
    {
        return [
            'constraint-missing' => 'The application does not declare a PHP version constraint.',
            'satisfied' => 'The current PHP version satisfies composer.json.',
            'unsatisfied' => Message::make(
                'PHP {version} does not satisfy [{constraint}].',
                'Switch PHP versions or update the composer.json PHP constraint.',
            )->link(Link::docs('installation', 'requirements')),
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        $constraint = $this->composer->phpConstraint();
        if ($constraint === null) {
            return $this->skip('constraint-missing');
        }

        return Semver::satisfies(PHP_VERSION, $constraint)
            ? $this->pass('satisfied')
            : $this->fail('unsatisfied', ['version' => PHP_VERSION, 'constraint' => $constraint]);
    }
}
