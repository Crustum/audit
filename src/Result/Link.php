<?php
declare(strict_types=1);

namespace Crustum\Audit\Result;

use Cake\Utility\Inflector;

/**
 * Related documentation or reference link.
 */
final readonly class Link
{
    /**
     * Constructor.
     *
     * @param string $label Human-readable link label.
     * @param string $url Browser URL.
     * @param string $agentUrl Agent-readable URL.
     */
    private function __construct(
        public string $label,
        public string $url,
        public string $agentUrl,
    ) {
    }

    /**
     * Create a link to an arbitrary URL.
     *
     * @param string $label Human-readable link label.
     * @param string $url Link URL.
     * @return self
     */
    public static function to(string $label, string $url): self
    {
        return new self($label, $url, $url);
    }

    /**
     * Create a link to a CakePHP 5 documentation page.
     *
     * @param string $page Page path relative to the CakePHP book.
     * @param string|null $section Optional section anchor.
     * @param string|null $label Optional custom label.
     * @return self
     */
    public static function docs(string $page, ?string $section = null, ?string $label = null): self
    {
        $pageUrl = 'https://book.cakephp.org/5/en/' . $page . '.html';

        return new self(
            $label ?? Inflector::humanize(basename($page)) . ' documentation',
            $pageUrl . ($section !== null ? '#' . $section : ''),
            $pageUrl,
        );
    }

    /**
     * Replace tokens in the link label and URLs.
     *
     * @param array<string, string> $tokens Replacement values keyed by token.
     * @return self
     */
    public function replace(array $tokens): self
    {
        return new self(
            strtr($this->label, $tokens),
            strtr($this->url, $tokens),
            strtr($this->agentUrl, $tokens),
        );
    }
}
