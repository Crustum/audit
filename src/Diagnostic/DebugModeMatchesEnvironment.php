<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Cake\Core\Configure;
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
 * Prevents debug mode from running in production.
 */
final class DebugModeMatchesEnvironment extends Diagnostic implements FixableInterface
{
    public string $name = 'Debug matches environment';

    public string $group = 'security';

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
        return [
            'enabled-in-production' => Message::make(
                'Debug mode is enabled in production.',
                'Set DEBUG=false in production.',
                'Set DEBUG=false in the application environment file?',
            )->link(Link::docs('development/configuration', 'debugging')),
            'matches' => 'Debug mode matches the application environment.',
            'already-disabled' => 'Debug mode is already disabled.',
            'disable-failed' => 'Debug mode could not be disabled in the application environment file.',
            'disabled' => 'Debug mode was disabled in the application environment file.',
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        return $this->debugEnabledInProduction()
            ? $this->fail('enabled-in-production')->fixable()
            : $this->pass('matches');
    }

    /**
     * @inheritDoc
     */
    public function fix(DiagnosticResult $result, ?string $option = null): FixResult
    {
        if (!$this->debugEnabledInProduction()) {
            return $this->fixed('already-disabled');
        }

        $path = EnvironmentFile::path('.env', $this->basePath);
        $contents = is_file($path) && is_writable($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            return $this->fixFailed('disable-failed')
                ->withDetails(sprintf('The environment file [%s] could not be updated.', $path));
        }

        $updated = preg_match('/^DEBUG=/m', $contents) === 1
            ? preg_replace('/^DEBUG=.*$/m', 'DEBUG=false', $contents)
            : $contents . ($contents !== '' && !str_ends_with($contents, PHP_EOL) ? PHP_EOL : '') . 'DEBUG=false' . PHP_EOL;
        if (!is_string($updated) || file_put_contents($path, $updated) === false) {
            return $this->fixFailed('disable-failed');
        }

        Configure::write('debug', false);

        return $this->fixed('disabled');
    }

    /**
     * Determine whether debug is active in production.
     *
     * @return bool
     */
    private function debugEnabledInProduction(): bool
    {
        return (bool)Configure::read('debug') && EnvironmentMode::current()->isProduction();
    }
}
