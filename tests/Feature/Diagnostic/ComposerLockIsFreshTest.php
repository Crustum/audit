<?php
declare(strict_types=1);

use Crustum\Audit\Diagnostic\ComposerLockIsFresh;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\FixResult;
use Crustum\Audit\Result\Status;

it('classifies stale Composer lock files and repairs them', function (): void {
    $fixture = audit_composer_fixture(
        'if (($argv[1] ?? "") === "validate") { fwrite(STDERR, "The lock file is not up to date."); exit(1); }',
    );
    $diagnostic = new ComposerLockIsFresh($fixture['composer'], $fixture['path']);
    $result = $diagnostic->check();

    expect($result->status)->toBe(Status::Fail)
        ->and($result->code)->toBe('composer-lock-is-fresh.stale')
        ->and($diagnostic->fix($result)->status)->toBe(Status::Pass);
});

it('offers to generate a missing Composer lock file locally', function (): void {
    $fixture = audit_composer_fixture(
        'if (($argv[1] ?? "") === "install") { file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "composer.lock", "{}"); exit(0); } exit(1);',
        false,
    );
    $diagnostic = new ComposerLockIsFresh($fixture['composer'], $fixture['path']);
    $result = $diagnostic->check();

    expect($result->status)->toBe(Status::Fail)
        ->and($result->code)->toBe('composer-lock-is-fresh.lock-missing')
        ->and($diagnostic->fix($result)->status)->toBe(Status::Pass)
        ->and(is_file($fixture['path'] . DS . 'composer.lock'))->toBeTrue();
});

it('reports Composer lock constraint mismatches', function (): void {
    $fixture = audit_composer_fixture(
        'fwrite(STDERR, "Required package \"vendor/package\" is not present in the lock file."); exit(1);',
    );
    $result = (new ComposerLockIsFresh($fixture['composer'], $fixture['path']))->check();

    expect($result->status)->toBe(Status::Fail)
        ->and($result->code)->toBe('composer-lock-is-fresh.constraint-mismatch')
        ->and($result->context['packages'] ?? null)->toBe(['vendor/package']);
});

it('does not report unrelated Composer validation errors as stale lock files', function (): void {
    $fixture = audit_composer_fixture(
        'fwrite(STDERR, "Invalid composer.json schema"); exit(1);',
    );
    $result = (new ComposerLockIsFresh($fixture['composer'], $fixture['path']))->check();

    expect($result->status)->toBe(Status::Fail)
        ->and($result->code)->toBe('composer-lock-is-fresh.inspection-failed');
});

it('reports when Composer cannot repair the lock file', function (): void {
    $fixture = audit_composer_fixture(
        'if (($argv[1] ?? "") === "validate") { fwrite(STDERR, "The lock file is not up to date."); exit(1); } fwrite(STDERR, "update failed"); exit(1);',
    );
    $diagnostic = new ComposerLockIsFresh($fixture['composer'], $fixture['path']);
    $result = $diagnostic->check();

    expect($result->status)->toBe(Status::Fail)
        ->and($diagnostic->fix($result)->status)->toBe(Status::Fail)
        ->and($diagnostic->fix($result)->details)->toContain('update failed');
});

it('rejects unexpected Composer lock fix outcomes', function (): void {
    $fixture = audit_composer_fixture('exit(0);');
    $diagnostic = new ComposerLockIsFresh($fixture['composer'], $fixture['path']);

    expect(fn(): FixResult => $diagnostic->fix(DiagnosticResult::fail('invalid', 'invalid')))
        ->toThrow(LogicException::class);
});
