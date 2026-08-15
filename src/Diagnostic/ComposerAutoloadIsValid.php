<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Crustum\Audit\Contract\FixableInterface;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\FixResult;
use Crustum\Audit\Result\Message;
use Crustum\Audit\Support\Composer;
use Crustum\Audit\Support\Details;
use Override;
use Throwable;

/**
 * Verifies Composer can build strict optimized autoload files.
 */
final class ComposerAutoloadIsValid extends Diagnostic implements FixableInterface
{
    public string $name = 'Composer autoload works';

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
            'dependencies-missing' => 'Composer dependencies are not installed.',
            'valid' => 'Composer can generate optimized autoload files.',
            'invalid' => Message::make(
                'Composer cannot generate valid autoload files.',
                'Regenerate Composer autoload files with `composer dump-autoload`.',
                'Regenerate Composer autoload files using `composer dump-autoload`?',
            ),
            'regeneration-failed' => 'Composer autoload files could not be regenerated.',
            'regenerated' => 'Composer autoload files were regenerated.',
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

        if (!is_file($this->path() . DS . 'vendor' . DS . 'autoload.php')) {
            return $this->skip('dependencies-missing');
        }

        $result = $this->run(['dump-autoload', '--dry-run', '--optimize', '--strict-psr']);

        return $result['successful']
            ? $this->pass('valid')
            : $this->fail('invalid')->fixable()->withDetails($result['details']);
    }

    /**
     * @inheritDoc
     */
    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        $execution = $this->run(['dump-autoload', '--optimize', '--strict-psr']);

        return $execution['successful']
            ? $this->fixed('regenerated')
            : $this->fixFailed('regeneration-failed')->withDetails($execution['details']);
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
                    'Composer exited without autoload details.',
                ),
            ];
        } catch (Throwable $throwable) {
            return ['successful' => false, 'details' => $throwable->getMessage()];
        }
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
