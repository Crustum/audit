<?php
declare(strict_types=1);

use Crustum\Audit\Diagnostic\ComposerAuditPasses;
use Crustum\Audit\Result\Status;

it('reports Composer security advisories', function (): void {
    $fixture = audit_composer_fixture(
        'echo json_encode(["advisories" => ["vendor/package" => [["id" => "CVE"]]]]); exit(1);',
    );
    $result = new ComposerAuditPasses($fixture['composer'], $fixture['path'])->check();

    expect($result->status)->toBe(Status::Fail)
        ->and($result->code)->toBe('composer-audit-passes.vulnerable')
        ->and($result->details)->toContain('1 security advisory');
});

it('passes when Composer audit reports no advisories', function (): void {
    $fixture = audit_composer_fixture('echo json_encode(["advisories" => []]); exit(0);');

    expect(new ComposerAuditPasses($fixture['composer'], $fixture['path'])->check()->status)
        ->toBe(Status::Pass);
});

it('skips Composer audit when the lock file is missing', function (): void {
    $fixture = audit_composer_fixture('exit(0);', false);

    expect(new ComposerAuditPasses($fixture['composer'], $fixture['path'])->check()->status)
        ->toBe(Status::Skip)
        ->and(new ComposerAuditPasses($fixture['composer'], $fixture['path'])->check()->code)
        ->toBe('composer-audit-passes.lock-missing');
});
