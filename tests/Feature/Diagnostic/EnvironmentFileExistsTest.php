<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Audit\Diagnostic\EnvironmentFileExists;
use Crustum\Audit\EnvironmentMode;
use Crustum\Audit\Result\Status;
use Crustum\Audit\Support\EnvironmentFile;

beforeEach(function (): void {
    audit_reset_environment_mode();
});

it('creates a missing environment file from its example', function (): void {
    $path = audit_environment_path();
    file_put_contents($path . DS . '.env.example', "DEBUG=false\n");
    $diagnostic = new EnvironmentFileExists($path);

    expect($diagnostic->check()->status)->toBe(Status::Fail)
        ->and($diagnostic->fix($diagnostic->check())->status)->toBe(Status::Pass)
        ->and(file_get_contents($path . DS . '.env'))->toBe("DEBUG=false\n");
});

it('passes when an environment file already exists', function (): void {
    $path = audit_environment_path();
    file_put_contents($path . DS . '.env', "DEBUG=false\n");

    expect(new EnvironmentFileExists($path)->check()->status)->toBe(Status::Pass);
});

it('creates an empty environment file when the example is missing', function (): void {
    $path = audit_environment_path();
    $diagnostic = new EnvironmentFileExists($path);
    $result = $diagnostic->check();

    expect($result->status)->toBe(Status::Fail)
        ->and($result->code)->toBe('environment-file-exists.missing')
        ->and($diagnostic->fix($result)->status)->toBe(Status::Pass)
        ->and(is_file($path . DS . '.env'))->toBeTrue()
        ->and((string)file_get_contents($path . DS . '.env'))->toBe('');
});

it('does not overwrite an existing environment file when fixed', function (): void {
    $path = audit_environment_path();
    file_put_contents($path . DS . '.env', "DEBUG=true\n");
    file_put_contents($path . DS . '.env.example', "DEBUG=false\n");
    $diagnostic = new EnvironmentFileExists($path);

    expect($diagnostic->fix($diagnostic->check())->status)->toBe(Status::Pass)
        ->and((string)file_get_contents($path . DS . '.env'))->toBe("DEBUG=true\n");
});

it('does not offer to create a missing environment file in production', function (): void {
    $path = audit_environment_path();
    Configure::write('Audit.environment', 'production');
    $result = new EnvironmentFileExists($path)->check();

    expect($result->status)->toBe(Status::Fail)
        ->and($result->fixableEnvironments)->toBe([EnvironmentMode::Local])
        ->and($result->fixableIn(EnvironmentMode::Production))->toBeFalse();
});

it('resolves the default environment directory from CONFIG', function (): void {
    expect(EnvironmentFile::directory())
        ->toBe(rtrim(CONFIG, '/\\'));
});
