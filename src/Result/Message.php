<?php
declare(strict_types=1);

namespace Crustum\Audit\Result;

/**
 * Reusable diagnostic message definition.
 */
final class Message
{
    /**
     * Related links.
     *
     * @var list<\Crustum\Audit\Result\Link>
     */
    public array $links = [];

    /**
     * Constructor.
     *
     * @param string $summary Result summary.
     * @param string|null $remediation Optional remediation text.
     * @param string|null $confirmation Optional fix confirmation prompt.
     */
    public function __construct(
        public readonly string $summary,
        public readonly ?string $remediation = null,
        public readonly ?string $confirmation = null,
    ) {
    }

    /**
     * Create a diagnostic message.
     *
     * @param string $summary Result summary.
     * @param string|null $remediation Optional remediation text.
     * @param string|null $confirmation Optional fix confirmation prompt.
     * @return self
     */
    public static function make(
        string $summary,
        ?string $remediation = null,
        ?string $confirmation = null,
    ): self {
        return new self($summary, $remediation, $confirmation);
    }

    /**
     * Add a related link.
     *
     * @param \Crustum\Audit\Result\Link $link Related link.
     * @return $this
     */
    public function link(Link $link): self
    {
        $this->links[] = $link;

        return $this;
    }
}
