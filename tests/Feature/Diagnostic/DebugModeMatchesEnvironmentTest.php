<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Audit\Diagnostic\DebugModeMatchesEnvironment;
use Crustum\Audit\Result\Status;

beforeEach(function (): void {
    audit_reset_environment_mode();
});

it('disables production debug mode through the environment file', function (): void {
    $path = audit_environment_path();
    file_put_contents($path . DS . '.env', "DEBUG=true\n");
    Configure::write('Audit.environment', 'production');
    Configure::write('debug', true);
    $diagnostic = new DebugModeMatchesEnvironment($path);

    expect($diagnostic->check()->status)->toBe(Status::Fail)
        ->and($diagnostic->fix($diagnostic->check())->status)->toBe(Status::Pass)
        ->and(file_get_contents($path . DS . '.env'))->toContain('DEBUG=false')
        ->and(Configure::read('debug'))->toBeFalse();
});

it('passes when debug mode is disabled in production', function (): void {
    Configure::write('Audit.environment', 'production');
    Configure::write('debug', false);

    expect((new DebugModeMatchesEnvironment())->check()->status)->toBe(Status::Pass);
});

it('allows debug mode in local environments', function (): void {
    Configure::write('Audit.environment', 'local');
    Configure::write('debug', true);

    expect((new DebugModeMatchesEnvironment())->check()->status)->toBe(Status::Pass);
});

it('treats unmapped environments as production for debug mode', function (): void {
    Configure::write('Audit.environment', 'staging');
    Configure::write('debug', true);

    expect((new DebugModeMatchesEnvironment())->check()->status)->toBe(Status::Fail);
});

it('honors custom local environment mappings for debug mode', function (): void {
    Configure::write('Audit.environments', [
        'local' => ['local', 'development'],
        'production' => ['production'],
    ]);
    Configure::write('Audit.environment', 'development');
    Configure::write('debug', true);

    expect((new DebugModeMatchesEnvironment())->check()->status)->toBe(Status::Pass);
});

it('adds DEBUG when it is missing from the environment file', function (): void {
    $path = audit_environment_path();
    file_put_contents($path . DS . '.env', "SECURITY_SALT=test\n");
    Configure::write('Audit.environment', 'production');
    Configure::write('debug', true);
    $diagnostic = new DebugModeMatchesEnvironment($path);

    expect($diagnostic->fix($diagnostic->check())->status)->toBe(Status::Pass)
        ->and((string)file_get_contents($path . DS . '.env'))->toContain('DEBUG=false');
});
