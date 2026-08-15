<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\Message;
use Crustum\Audit\Support\ComposerJson;
use Override;

/**
 * Reports missing Composer-suggested PHP extensions.
 */
final class RecommendedPhpExtensionsAreLoaded extends Diagnostic
{
    public string $name = 'Recommended PHP extensions loaded';

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
            'none-declared' => 'No Composer-suggested PHP extensions are declared.',
            'installed' => 'All Composer-suggested PHP extensions are installed.',
            'missing' => Message::make(
                'Some Composer-suggested PHP extensions are missing.',
                'Install the suggested extensions when their dependent features are used.',
            ),
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        $extensions = $this->composer->suggestedExtensions();
        if ($extensions === []) {
            return $this->pass('none-declared');
        }

        $missing = array_values(array_filter(
            $extensions,
            static fn(string $extension): bool => !extension_loaded($extension),
        ));

        return $missing === []
            ? $this->pass('installed')
            : $this->warn('missing')->withDetails(implode(PHP_EOL, array_map(
                static fn(string $extension): string => '- ext-' . $extension,
                $missing,
            )));
    }
}
