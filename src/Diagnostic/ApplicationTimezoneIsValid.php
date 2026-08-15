<?php
declare(strict_types=1);

namespace Crustum\Audit\Diagnostic;

use Cake\Core\Configure;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\Link;
use Crustum\Audit\Result\Message;
use Override;

/**
 * Verifies the configured application timezone.
 */
final class ApplicationTimezoneIsValid extends Diagnostic
{
    public string $name = 'App timezone is valid';

    public string $group = 'environment';

    /**
     * @inheritDoc
     */
    #[Override]
    protected function messages(): array
    {
        $guidance = 'Set App.defaultTimezone to a valid PHP timezone.';
        $link = Link::to('PHP timezone list', 'https://www.php.net/manual/en/timezones.php');

        return [
            'missing' => Message::make('The application does not have a timezone configured.', $guidance)->link($link),
            'valid' => 'The application has a valid timezone.',
            'invalid' => Message::make(
                'The application timezone [{timezone}] is not a valid PHP timezone.',
                $guidance,
            )->link($link),
        ];
    }

    /**
     * @inheritDoc
     */
    public function check(): DiagnosticResult
    {
        $timezone = Configure::read('App.defaultTimezone');

        if (!is_string($timezone) || $timezone === '') {
            return $this->fail('missing');
        }

        return in_array($timezone, timezone_identifiers_list(), true)
            ? $this->pass('valid')
            : $this->fail('invalid', ['timezone' => $timezone]);
    }
}
