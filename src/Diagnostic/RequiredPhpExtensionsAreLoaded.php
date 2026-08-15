<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\Message;
use Crustum\Audit\Support\ComposerJson;
use Override;

/**
 * Verifies Composer-required PHP extensions.
 */
final class RequiredPhpExtensionsAreLoaded extends Diagnostic
{
    public string $name = 'Required PHP extensions loaded';

    public string $group = 'environment';

    /**
     * Constructor.
     *
     * @param \Crustum\Audit\Support\ComposerJson $composer Composer metadata.
     */
    public function __construct(private readonly ComposerJson $composer)
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
            'composer-unreadable' => 'The application does not have a readable composer.json file.',
            'installed' => 'All Composer-required PHP extensions are installed.',
            'missing' => Message::make(
                'Some Composer-required PHP extensions are missing.',
                'Install the missing PHP extensions for the PHP binary running CakePHP.',
            ),
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        if ($this->composer->contents() === null) {
            return $this->skip('composer-unreadable');
        }

        $missing = array_values(array_filter(
            $this->composer->requiredExtensions(),
            static fn(string $extension): bool => !extension_loaded($extension),
        ));

        return $missing === []
            ? $this->pass('installed')
            : $this->fail('missing')->withDetails(implode(PHP_EOL, array_map(
                static fn(string $extension): string => '- ext-' . $extension,
                $missing,
            )));
    }
}
