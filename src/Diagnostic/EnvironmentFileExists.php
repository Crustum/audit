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
use Crustum\Audit\Support\EnvironmentFile;
use Override;

/**
 * Verifies an application environment file exists.
 */
final class EnvironmentFileExists extends Diagnostic implements FixableInterface
{
    public string $name = '.env file exists';

    public string $group = 'environment';

    /**
     * Constructor.
     *
     * @param string|null $basePath Audited environment directory.
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
            'exists' => 'The application has an environment file.',
            'missing' => Message::make(
                'The application does not have an environment file.',
                'Create a .env file with the application environment variables.',
                'Create an empty .env file?',
            )->link($link),
            'missing-with-example' => Message::make(
                'The application does not have an environment file.',
                'Copy .env.example to .env, then review the copied values.',
                'Copy .env.example to .env?',
            )->link($link),
            'already-exists' => 'The .env file already exists.',
            'creation-failed' => 'The .env file could not be created from .env.example.',
            'created' => 'The .env file was created from .env.example.',
            'empty-creation-failed' => 'The empty .env file could not be created.',
            'empty-created' => 'An empty .env file was created.',
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        $files = array_filter(
            glob($this->path('.env*')) ?: [],
            static fn(string $file): bool => is_file($file) && !str_ends_with($file, '.example'),
        );
        if ($files !== []) {
            return $this->pass('exists');
        }

        return is_file($this->path('.env.example'))
            ? $this->fail('missing-with-example')->fixable(EnvironmentMode::Local)
            : $this->fail('missing')->fixable(EnvironmentMode::Local);
    }

    /**
     * @inheritDoc
     */
    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        $target = $this->path('.env');
        $example = $this->path('.env.example');
        if (is_file($target)) {
            return $this->fixed('already-exists');
        }

        if (is_file($example)) {
            return copy($example, $target)
                ? $this->fixed('created')
                : $this->fixFailed('creation-failed');
        }

        return file_put_contents($target, '') !== false
            ? $this->fixed('empty-created')
            : $this->fixFailed('empty-creation-failed');
    }

    /**
     * Build an environment file path under CONFIG (or the test override).
     *
     * @param string $relative Relative file name.
     * @return string
     */
    private function path(string $relative): string
    {
        return EnvironmentFile::path($relative, $this->basePath);
    }
}
