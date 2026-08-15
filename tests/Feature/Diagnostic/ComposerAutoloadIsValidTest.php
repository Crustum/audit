<?php
declare(strict_types=1);

use Crustum\Audit\Diagnostic\ComposerAutoloadIsValid;
use Crustum\Audit\Result\Status;
use Crustum\Audit\Support\Composer;

it('checks and regenerates Composer autoload files', function (): void {
    $fixture = audit_composer_fixture(
        'if (in_array("--dry-run", $argv, true)) { fwrite(STDERR, "PSR-4 failure"); exit(1); } echo "Generated";',
    );
    $diagnostic = new ComposerAutoloadIsValid($fixture['composer'], $fixture['path']);
    $result = $diagnostic->check();

    expect($result->status)->toBe(Status::Fail)
        ->and($result->details)->toContain('PSR-4 failure')
        ->and($diagnostic->fix($result)->status)->toBe(Status::Pass);
});

it('passes when Composer autoload dry-run succeeds', function (): void {
    $fixture = audit_composer_fixture('exit(0);');

    expect((new ComposerAutoloadIsValid($fixture['composer'], $fixture['path']))->check()->status)
        ->toBe(Status::Pass);
});

it('skips Composer autoload when dependencies are missing', function (): void {
    $path = TMP . 'audit-composer-' . str_replace('.', '', uniqid('', true));
    mkdir($path, 0775, true);
    file_put_contents($path . DS . 'composer.json', '{}');
    $executable = $path . DS . 'composer.php';
    file_put_contents($executable, "<?php\nexit(0);");
    $composer = new Composer([PHP_BINARY, $executable], $path);

    expect((new ComposerAutoloadIsValid($composer, $path))->check()->status)->toBe(Status::Skip)
        ->and((new ComposerAutoloadIsValid($composer, $path))->check()->code)
        ->toBe('composer-autoload-is-valid.dependencies-missing');
});
