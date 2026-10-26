<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Audit\Diagnostic\EnvironmentFileIsGitIgnored;
use Crustum\Audit\EnvironmentMode;
use Crustum\Audit\Result\Status;

beforeEach(function (): void {
    audit_reset_environment_mode();
});

it('adds environment files to gitignore', function (): void {
    $path = audit_environment_path();
    file_put_contents($path . DS . '.gitignore', "/vendor\n");
    $diagnostic = new EnvironmentFileIsGitIgnored($path);

    expect($diagnostic->check()->status)->toBe(Status::Fail)
        ->and($diagnostic->fix($diagnostic->check())->status)->toBe(Status::Pass)
        ->and($diagnostic->check()->status)->toBe(Status::Pass)
        ->and((string)file_get_contents($path . DS . '.gitignore'))->toContain('/config/.env');
});

it('accepts config/.env gitignore patterns', function (): void {
    $path = audit_environment_path();
    file_put_contents($path . DS . '.gitignore', "/config/.env\n");

    expect(new EnvironmentFileIsGitIgnored($path)->check()->status)->toBe(Status::Pass);
});

it('creates a gitignore that ignores environment files when missing', function (): void {
    $path = audit_environment_path();
    $diagnostic = new EnvironmentFileIsGitIgnored($path);
    $result = $diagnostic->check();

    expect($result->status)->toBe(Status::Fail)
        ->and($result->code)->toBe('environment-file-is-git-ignored.gitignore-missing')
        ->and($diagnostic->fix($result)->status)->toBe(Status::Pass)
        ->and((string)file_get_contents($path . DS . '.gitignore'))->toContain('/config/.env');
});

it('does not offer to update gitignore in production', function (): void {
    $path = audit_environment_path();
    file_put_contents($path . DS . '.gitignore', "/vendor\n");
    Configure::write('Audit.environment', 'production');
    $result = new EnvironmentFileIsGitIgnored($path)->check();

    expect($result->status)->toBe(Status::Fail)
        ->and($result->fixableEnvironments)->toBe([EnvironmentMode::Local])
        ->and($result->fixableIn(EnvironmentMode::Production))->toBeFalse();
});
