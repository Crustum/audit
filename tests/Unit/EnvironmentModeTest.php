<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Audit\EnvironmentMode;

afterEach(function (): void {
    Configure::delete('Audit.environment');
    Configure::delete('Audit.environments');
});

it('identifies production expectations', function (): void {
    expect(EnvironmentMode::Production->isProduction())->toBeTrue()
        ->and(EnvironmentMode::Local->isProduction())->toBeFalse();
});

it('resolves configured environment modes', function (): void {
    Configure::write('Audit.environments', [
        'local' => ['dev'],
        'production' => ['staging'],
    ]);

    expect(EnvironmentMode::fromApplicationEnvironment('dev'))->toBe(EnvironmentMode::Local)
        ->and(EnvironmentMode::fromApplicationEnvironment('staging'))->toBe(EnvironmentMode::Production);
});

it('resolves the current configured environment', function (): void {
    Configure::write('Audit.environment', 'test');
    Configure::write('Audit.environments.local', ['test']);

    expect(EnvironmentMode::current())->toBe(EnvironmentMode::Local);
});

it('treats unmapped environments as production', function (): void {
    expect(EnvironmentMode::fromApplicationEnvironment('preview'))->toBe(EnvironmentMode::Production);
});

it('rejects invalid configured environment modes', function (): void {
    Configure::write('Audit.environments.lcoal', ['local']);

    EnvironmentMode::fromApplicationEnvironment('local');
})->throws(InvalidArgumentException::class, 'Invalid Audit environment mode [lcoal] configured.');

it('rejects environment names that are not configured as arrays', function (): void {
    Configure::write('Audit.environments.local', 'local');

    EnvironmentMode::fromApplicationEnvironment('local');
})->throws(
    InvalidArgumentException::class,
    'Application environment names for Audit mode [local] must be an array, [string] given.',
);

it('rejects environment names assigned to multiple modes', function (): void {
    Configure::write('Audit.environments', [
        'local' => ['shared'],
        'production' => ['shared'],
    ]);

    EnvironmentMode::fromApplicationEnvironment('shared');
})->throws(
    InvalidArgumentException::class,
    'Application environment [shared] is configured for multiple Audit modes.',
);
