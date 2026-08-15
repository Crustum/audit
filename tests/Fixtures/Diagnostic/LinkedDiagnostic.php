<?php
declare(strict_types=1);

namespace Crustum\Audit\Test\Fixtures\Diagnostic;

use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\Link;
use Crustum\Audit\Result\Message;
use Override;

/**
 * Named-message diagnostic fixture.
 */
class LinkedDiagnostic extends Diagnostic
{
    /**
     * @var string
     */
    public string $name = 'Testing diagnostic has links';

    /**
     * @var string
     */
    public string $group = 'testing';

    /**
     * @inheritDoc
     */
    #[Override]
    protected function messages(): array
    {
        return [
            'warning' => Message::make(
                summary: 'The linked diagnostic warned about {topic}.',
                remediation: 'Follow the {topic} documentation.',
            )->link(Link::to('Related {topic} guide', 'https://example.com/{topic}')),
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        return $this->warn('warning', ['topic' => 'cache']);
    }
}
