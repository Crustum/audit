<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\Link;
use Crustum\Audit\Result\Message;
use Override;
use Throwable;

/**
 * Verifies PHP configuration files can be evaluated.
 *
 * Cake's `config/` directory mixes return-array Configure files with bootstrap
 * scripts (`paths.php`, `bootstrap.php`, `routes.php`, …). Only array-style
 * config files are required; bootstrap scripts are skipped because they are
 * already loaded for the running application.
 */
final class ConfigurationFilesCanBeLoaded extends Diagnostic
{
    /**
     * Basenames that define constants or reboot the application.
     *
     * @var list<string>
     */
    private const array SKIP_FILES = [
        'bootstrap.php',
        'bootstrap_cli.php',
        'cli.php',
        'paths.php',
        'routes.php',
    ];

    public string $name = 'Config files load';

    public string $group = 'configuration';

    /**
     * Constructor.
     *
     * @param string|null $configPath Configuration directory.
     */
    public function __construct(private readonly ?string $configPath = null)
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
            'missing' => 'The application does not have configuration files.',
            'load-failed' => Message::make('A configuration file could not be loaded.')
                ->link(Link::docs('development/configuration')),
            'loaded' => 'Configuration files can be loaded.',
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        $files = array_values(array_filter(
            glob(($this->configPath ?? CONFIG) . '*.php') ?: [],
            fn(string $file): bool => !in_array(basename($file), self::SKIP_FILES, true),
        ));
        if ($files === []) {
            return $this->skip('missing');
        }

        foreach ($files as $file) {
            try {
                (static fn(string $path): mixed => require $path)($file);
            } catch (Throwable $exception) {
                return $this->fail('load-failed')->withDetails(sprintf(
                    '%s: %s',
                    basename($file),
                    $exception->getMessage(),
                ));
            }
        }

        return $this->pass('loaded');
    }
}
