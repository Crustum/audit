<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Crustum\Audit\Contract\FixableInterface;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\FixResult;
use Crustum\Audit\Result\Message;
use Crustum\Audit\Support\Details;
use Override;

/**
 * Verifies CakePHP runtime directories are writable.
 */
final class StorageIsWritable extends Diagnostic implements FixableInterface
{
    public string $name = 'Storage is writable';

    public string $group = 'storage';

    /**
     * Constructor.
     *
     * @param array<string, string>|null $paths Labels mapped to runtime paths.
     */
    public function __construct(private readonly ?array $paths = null)
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
            'writable' => 'The application runtime directories are writable.',
            'not-writable' => Message::make(
                'The application cannot write to every required runtime directory.',
                'Ensure TMP, CACHE, and LOGS exist and are writable by PHP.',
                'Create missing runtime directories and make them writable?',
            ),
            'still-not-writable' => 'Some runtime directories are still not writable.',
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        $failures = $this->failures();

        return $failures === []
            ? $this->pass('writable')
            : $this->fail('not-writable')->fixable()->withDetails(Details::failures($failures));
    }

    /**
     * @inheritDoc
     */
    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        foreach ($this->runtimePaths() as $path) {
            if (!is_dir($path)) {
                mkdir($path, 0775, true);
            }

            if (!is_dir($path)) {
                continue;
            }

            if (is_writable($path)) {
                chmod($path, 0775);
            }
        }

        $failures = $this->failures();

        return $failures === []
            ? $this->fixed('writable')
            : $this->fixFailed('still-not-writable')->withDetails(Details::failures($failures));
    }

    /**
     * Get runtime paths.
     *
     * @return array<string, string>
     */
    private function runtimePaths(): array
    {
        return $this->paths ?? ['tmp/' => TMP, 'cache/' => CACHE, 'logs/' => LOGS];
    }

    /**
     * Get path failures.
     *
     * @return array<string, string>
     */
    private function failures(): array
    {
        $failures = [];
        foreach ($this->runtimePaths() as $label => $path) {
            if (!is_dir($path)) {
                $failures[$label] = 'directory does not exist';
            } elseif (!$this->canWrite($path)) {
                $failures[$label] = 'directory is not writable';
            }
        }

        return $failures;
    }

    /**
     * Probe a directory write.
     *
     * @param string $path Directory path.
     * @return bool
     */
    private function canWrite(string $path): bool
    {
        $probe = rtrim($path, '\\/') . DS . '.audit-write-' . str_replace('.', '', uniqid('', true));
        if (!is_writable($path) || file_put_contents($probe, '1') === false) {
            return false;
        }

        return unlink($probe);
    }
}
