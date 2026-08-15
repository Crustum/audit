<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\Message;
use Crustum\Audit\Support\Composer;
use Crustum\Audit\Support\Details;
use Override;
use Throwable;

/**
 * Runs Composer's security advisory audit.
 */
final class ComposerAuditPasses extends Diagnostic
{
    public string $name = 'Composer audit passes';

    public string $group = 'security';

    /**
     * Constructor.
     *
     * @param \Crustum\Audit\Support\Composer $composer Composer process factory.
     * @param string|null $basePath Audited application path.
     */
    public function __construct(
        private readonly Composer $composer,
        private readonly ?string $basePath = null,
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function messages(): array
    {
        return [
            'lock-missing' => 'The application does not have a composer.lock file.',
            'vulnerable' => Message::make(
                'Composer audit found vulnerable dependencies.',
                'Update or replace the vulnerable packages reported by `composer audit`.',
            ),
            'audit-failed' => Message::make(
                'Composer audit could not be completed.',
                'Run `composer audit` locally and resolve the reported issue.',
            ),
            'clean' => 'Composer audit did not find vulnerable dependencies.',
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        if (!is_file($this->path() . DS . 'composer.lock')) {
            return $this->skip('lock-missing');
        }

        try {
            $process = $this->composer->process(['audit', '--format=json', '--no-interaction'], 10.0);
            $process->run();
        } catch (Throwable $throwable) {
            return $this->fail('audit-failed')->withDetails($throwable->getMessage());
        }

        $advisories = $this->advisoryCount($process->getOutput());
        if ($advisories > 0) {
            return $this->fail('vulnerable')->withDetails(sprintf(
                '%d security %s %s reported.',
                $advisories,
                $advisories === 1 ? 'advisory' : 'advisories',
                $advisories === 1 ? 'was' : 'were',
            ));
        }

        return $process->isSuccessful()
            ? $this->pass('clean')
            : $this->fail('audit-failed')->withDetails(Details::processOutput(
                $process->getOutput(),
                $process->getErrorOutput(),
                'Composer exited without audit details.',
            ));
    }

    /**
     * Count advisories in Composer JSON output.
     *
     * @param string $output Composer output.
     * @return int
     */
    private function advisoryCount(string $output): int
    {
        $decoded = json_decode($output, true);
        $groups = is_array($decoded) ? ($decoded['advisories'] ?? []) : [];

        return is_array($groups) ? array_sum(array_map(
            static fn(mixed $group): int => is_array($group) ? count($group) : 0,
            $groups,
        )) : 0;
    }

    /**
     * Get the audited application path.
     *
     * @return string
     */
    private function path(): string
    {
        return $this->basePath ?? ROOT;
    }
}
