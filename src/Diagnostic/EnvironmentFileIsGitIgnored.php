<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Crustum\Audit\Contract\FixableInterface;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\EnvironmentMode;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\FixResult;
use Crustum\Audit\Result\Link;
use Crustum\Audit\Result\Message;
use Override;

/**
 * Verifies environment files are ignored by Git.
 */
final class EnvironmentFileIsGitIgnored extends Diagnostic implements FixableInterface
{
    public string $name = '.env is gitignored';

    public string $group = 'security';

    /**
     * Constructor.
     *
     * @param string|null $basePath Audited application path.
     */
    public function __construct(private readonly ?string $basePath = null)
    {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function messages(): array
    {
        $link = Link::docs('development/configuration', 'environment-variables');

        return [
            'gitignore-missing' => Message::make(
                'The application does not have a .gitignore file.',
                'Create a .gitignore file that includes .env.',
                'Create a .gitignore file that ignores .env?',
            )->link($link),
            'ignored' => 'Environment files are ignored by Git.',
            'not-ignored' => Message::make(
                'Environment files are not ignored by Git.',
                'Add /config/.env to .gitignore so secrets are not committed.',
                'Add /config/.env to .gitignore?',
            )->link($link),
            'already-ignored' => 'Environment files are already ignored by Git.',
            'update-failed' => 'The .gitignore file could not be updated.',
            'updated' => '/config/.env was added to .gitignore.',
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        if (!is_file($this->path())) {
            return $this->fail('gitignore-missing')->fixable(EnvironmentMode::Local);
        }

        return $this->ignoresEnvironment()
            ? $this->pass('ignored')
            : $this->fail('not-ignored')->fixable(EnvironmentMode::Local);
    }

    /**
     * @inheritDoc
     */
    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        if (is_file($this->path()) && $this->ignoresEnvironment()) {
            return $this->fixed('already-ignored');
        }

        $contents = is_file($this->path()) ? file_get_contents($this->path()) : '';
        if (!is_string($contents)) {
            return $this->fixFailed('update-failed');
        }

        $updated = $contents
            . ($contents !== '' && !str_ends_with($contents, PHP_EOL) ? PHP_EOL : '')
            . '/config/.env'
            . PHP_EOL;

        return file_put_contents($this->path(), $updated) !== false
            ? $this->fixed('updated')
            : $this->fixFailed('update-failed');
    }

    /**
     * Determine whether environment files are ignored.
     *
     * @return bool
     */
    private function ignoresEnvironment(): bool
    {
        $ignored = false;
        foreach (file($this->path(), FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $pattern = ltrim(trim($line), '/');
            if ($pattern === '') {
                continue;
            }

            if (str_starts_with($pattern, '#')) {
                continue;
            }

            $matches = $this->matchesEnvironmentPattern($pattern);
            if (str_starts_with($pattern, '!')) {
                if ($this->matchesEnvironmentPattern(substr($pattern, 1))) {
                    $ignored = false;
                }
            } elseif ($matches) {
                $ignored = true;
            }
        }

        return $ignored;
    }

    /**
     * Determine whether a gitignore pattern covers Cake environment files.
     *
     * @param string $pattern Normalized gitignore pattern.
     * @return bool
     */
    private function matchesEnvironmentPattern(string $pattern): bool
    {
        $pattern = ltrim($pattern, '/');

        return array_any(
            ['.env', 'config/.env', '*.env'],
            static fn(string $target): bool => fnmatch($pattern, $target),
        );
    }

    /**
     * Get the .gitignore path.
     *
     * @return string
     */
    private function path(): string
    {
        return ($this->basePath ?? ROOT) . DS . '.gitignore';
    }
}
