<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Audit\Diagnostic\ApplicationTimezoneIsValid;
use Crustum\Audit\Result\Status;

it('validates configured application timezones', function (): void {
    Configure::write('App.defaultTimezone', 'Invalid/Timezone');
    expect((new ApplicationTimezoneIsValid())->check()->status)->toBe(Status::Fail);

    Configure::write('App.defaultTimezone', 'UTC');
    expect((new ApplicationTimezoneIsValid())->check()->status)->toBe(Status::Pass);
});

it('passes when App.defaultTimezone matches the PHP default', function (): void {
    Configure::write('App.defaultTimezone', date_default_timezone_get());

    expect((new ApplicationTimezoneIsValid())->check()->status)->toBe(Status::Pass);
});
