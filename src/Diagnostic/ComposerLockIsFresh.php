<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Crustum\Audit\Contract\FixableInterface;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\EnvironmentMode;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\FixResult;
use Crustum\Audit\Result\Message;
use Crustum\Audit\Support\Composer;
use Crustum\Audit\Support\Details;
use LogicException;
use Override;
use Throwable;

/**
 * Verifies composer.lock matches composer.json.
 */
final class ComposerLockIsFresh extends Diagnostic implements FixableInterface
{
    public string $name = 'composer.lock is fresh';

    public string $group = 'composer';

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
            'composer-missing' => 'The application does not have a composer.json file.',
            'lock-missing' => Message::make(
                'The application does not have a composer.lock file.',
                'Run `composer install` to generate a composer.lock file.',
                'Generate composer.lock and install dependencies using `composer install`?',
            ),
            'fresh' => 'composer.lock is present and fresh.',
            'stale' => Message::make(
                'composer.lock is out of date with composer.json.',
                'Run `composer update --lock` to refresh the lock file.',
                'Refresh composer.lock using `composer update --lock`?',
            ),
            'constraint-mismatch' => Message::make(
                'composer.lock does not satisfy the package constraints in composer.json.',
                'Run `composer update {packages}` to lock satisfying versions.',
                'Update {packages} using `composer update {packages}`?',
            ),
            'inspection-failed' => Message::make(
                'Composer could not verify composer.lock freshness.',
                'Run `composer validate --check-lock` and resolve the reported errors.',
            ),
            'fix-failed' => 'composer.lock could not be repaired.',
            'fixed' => 'composer.lock was repaired.',
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        if (!is_file($this->path() . DS . 'composer.json')) {
            return $this->skip('composer-missing');
        }

        if (!is_file($this->path() . DS . 'composer.lock')) {
            return $this->fail('lock-missing')->fixable(EnvironmentMode::Local);
        }

        $execution = $this->run(['validate', '--check-lock', '--no-check-publish', '--no-check-all', '--no-ansi']);
        if ($execution['successful']) {
            return $this->pass('fresh');
        }

        $packages = $this->mismatchedPackages($execution['details']);
        if ($packages !== []) {
            return $this->fail('constraint-mismatch', ['packages' => implode(' ', $packages)])
                ->fixable(EnvironmentMode::Local)
                ->withContext('packages', $packages)
                ->withDetails($this->lockFileErrors($execution['details']) ?? $execution['details']);
        }

        if (
            str_contains($execution['details'], '# Lock file errors')
            || str_contains($execution['details'], 'lock file is not up to date')
        ) {
            return $this->fail('stale')->fixable(EnvironmentMode::Local)
                ->withDetails($this->lockFileErrors($execution['details']) ?? $execution['details']);
        }

        return $this->fail('inspection-failed')->withDetails($execution['details']);
    }

    /**
     * @inheritDoc
     */
    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        $arguments = match ($result->code) {
            'composer-lock-is-fresh.lock-missing' => ['install'],
            'composer-lock-is-fresh.stale' => ['update', '--lock'],
            'composer-lock-is-fresh.constraint-mismatch' => [
                'update',
                ...$this->contextPackages($result),
            ],
            default => throw new LogicException("Unexpected composer.lock result [{$result->code}]."),
        };
        $execution = $this->run($arguments);

        return $execution['successful']
            ? $this->fixed('fixed')
            : $this->fixFailed('fix-failed')->withDetails($execution['details']);
    }

    /**
     * Execute Composer.
     *
     * @param list<string> $arguments Composer arguments.
     * @return array{successful: bool, details: string}
     */
    private function run(array $arguments): array
    {
        try {
            $process = $this->composer->process([...$arguments, '--no-interaction']);
            $process->run();

            return [
                'successful' => $process->isSuccessful(),
                'details' => Details::processOutput(
                    $process->getOutput(),
                    $process->getErrorOutput(),
                    'Composer exited without lock file details.',
                ),
            ];
        } catch (Throwable $throwable) {
            return ['successful' => false, 'details' => $throwable->getMessage()];
        }
    }

    /**
     * Extract mismatched package names.
     *
     * @param string $details Composer output.
     * @return list<string>
     */
    private function mismatchedPackages(string $details): array
    {
        preg_match_all('/Required (?:dev )?package "([^"]+)"/', $details, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Extract factual lock error lines.
     *
     * @param string $details Composer output.
     * @return string|null
     */
    private function lockFileErrors(string $details): ?string
    {
        $lines = array_filter(
            array_map(trim(...), preg_split('/\R/', $details) ?: []),
            static fn(string $line): bool => str_starts_with($line, '- '),
        );

        return $lines === [] ? null : implode(PHP_EOL, array_map(
            static fn(string $line): string => preg_replace(
                '/, it is recommended.*$/',
                '.',
                $line,
            ) ?? $line,
            $lines,
        ));
    }

    /**
     * Read packages from result context.
     *
     * @param \Crustum\Audit\Result\DiagnosticResult $result Diagnostic result.
     * @return list<string>
     */
    private function contextPackages(DiagnosticResult $result): array
    {
        $packages = $result->context['packages'] ?? [];

        return is_array($packages) ? array_values(array_filter($packages, is_string(...))) : [];
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
