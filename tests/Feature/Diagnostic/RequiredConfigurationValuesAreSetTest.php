<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Audit\Diagnostic\RequiredConfigurationValuesAreSet;
use Crustum\Audit\Result\Status;

it('reports host-declared required configuration values', function (): void {
    Configure::write('Audit.requiredConfiguration', ['Services.apiKey' => 'API service']);
    Configure::delete('Services.apiKey');
    expect((new RequiredConfigurationValuesAreSet())->check()->status)->toBe(Status::Fail);

    Configure::write('Services.apiKey', 'secret');
    expect((new RequiredConfigurationValuesAreSet())->check()->status)->toBe(Status::Pass);
});

it('passes when no host-declared required configuration is configured', function (): void {
    Configure::write('Audit.requiredConfiguration', []);

    expect((new RequiredConfigurationValuesAreSet())->check()->status)->toBe(Status::Pass);
});

it('accepts list-style required configuration keys', function (): void {
    Configure::write('Audit.requiredConfiguration', ['Services.listKey']);
    Configure::delete('Services.listKey');
    expect((new RequiredConfigurationValuesAreSet())->check()->status)->toBe(Status::Fail);

    Configure::write('Services.listKey', 'present');
    expect((new RequiredConfigurationValuesAreSet())->check()->status)->toBe(Status::Pass);
});
