<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Utility\Security;
use Crustum\Audit\Diagnostic\ApplicationSecretIsSet;
use Crustum\Audit\EnvironmentMode;
use Crustum\Audit\Result\Status;

beforeEach(function (): void {
    audit_reset_environment_mode();
});

it('generates a local application secret in the environment file', function (): void {
    $path = audit_environment_path();
    file_put_contents($path . DS . '.env', "SECURITY_SALT=\n");
    $diagnostic = new ApplicationSecretIsSet($path);

    without_application_secret(function () use ($diagnostic, $path): void {
        expect($diagnostic->check()->status)->toBe(Status::Fail)
            ->and($diagnostic->fix($diagnostic->check())->status)->toBe(Status::Pass)
            ->and((string)file_get_contents($path . DS . '.env'))->toContain('SECURITY_SALT=')
            ->and((string)env('SECURITY_SALT'))->not->toBe('');
    });
});

it('does not mark a missing application secret fixable in production', function (): void {
    $path = audit_environment_path();
    file_put_contents($path . DS . '.env', "SECURITY_SALT=\n");
    Configure::write('Audit.environment', 'production');

    without_application_secret(function () use ($path): void {
        $result = (new ApplicationSecretIsSet($path))->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->fixableEnvironments)->toBe([EnvironmentMode::Local])
            ->and($result->fixableIn(EnvironmentMode::Production))->toBeFalse();
    });
});

it('reports when the application secret cannot be written to the environment file', function (): void {
    $path = audit_environment_path();
    $diagnostic = new ApplicationSecretIsSet($path);

    without_application_secret(function () use ($diagnostic, $path): void {
        $result = $diagnostic->check();
        expect($result->status)->toBe(Status::Fail)
            ->and($diagnostic->fix($result)->status)->toBe(Status::Fail)
            ->and($diagnostic->fix($result)->details)->toContain($path);
    });
});

it('passes when SECURITY_SALT is present in the environment', function (): void {
    without_application_secret(function (): void {
        putenv('SECURITY_SALT=audit-test-salt-value');
        $_ENV['SECURITY_SALT'] = 'audit-test-salt-value';

        expect((new ApplicationSecretIsSet())->check()->status)->toBe(Status::Pass);
    });
});

it('passes when Security::getSalt is set after Configure::consume', function (): void {
    without_application_secret(function (): void {
        Security::setSalt('runtime-consumed-salt');

        expect((new ApplicationSecretIsSet())->check()->status)->toBe(Status::Pass);
    });
});

it('reports a commented-out Dotenv loader when .env defines SECURITY_SALT', function (): void {
    $path = audit_environment_path();
    file_put_contents($path . DS . '.env', "SECURITY_SALT=from-file-only\n");
    file_put_contents($path . DS . 'bootstrap.php', <<<'PHP'
<?php
/*
if (!env('APP_NAME') && file_exists(CONFIG . '.env')) {
    $dotenv = new \josegonzalez\Dotenv\Loader([CONFIG . '.env']);
    $dotenv->parse()->putenv()->toEnv()->toServer();
}
*/
PHP);

    without_application_secret(function () use ($path): void {
        $result = (new ApplicationSecretIsSet($path))->check();

        expect($result->status)->toBe(Status::Fail)
            ->and($result->code)->toBe('application-secret-is-set.dotenv-disabled');
    });
});
