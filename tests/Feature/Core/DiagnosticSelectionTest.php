<?php
declare(strict_types=1);

use Crustum\Audit\DiagnosticSelection;
use Crustum\Audit\Test\Fixtures\Diagnostic\DatabaseDiagnostic;
use Crustum\Audit\Test\Fixtures\Diagnostic\PackagedDiagnostic;
use Crustum\Audit\Test\Fixtures\Diagnostic\PassingDiagnostic;

it('filters diagnostics by group', function (): void {
    $report = auditManager([
        PassingDiagnostic::class,
        DatabaseDiagnostic::class,
    ])->only('database')->run();

    expect($report->diagnostics())->toHaveCount(1)
        ->and($report->diagnostics()[0]->diagnostic::class)->toBe(DatabaseDiagnostic::class);
});

it('normalizes class selectors and exclusions', function (): void {
    $report = auditManager([
        PassingDiagnostic::class,
        DatabaseDiagnostic::class,
    ])
        ->only('PassingDiagnostic, DatabaseDiagnostic')
        ->except('database')
        ->run();

    expect($report->diagnostics())->toHaveCount(1)
        ->and($report->diagnostics()[0]->diagnostic::class)->toBe(PassingDiagnostic::class);
});

it('filters diagnostics by package and wildcard', function (): void {
    $packaged = new PackagedDiagnostic();

    expect(DiagnosticSelection::make(only: ['vendor/package'])->matches($packaged))->toBeTrue()
        ->and(DiagnosticSelection::make(only: ['vendor/*'])->matches($packaged))->toBeTrue()
        ->and(DiagnosticSelection::make(except: ['vendor/package'])->matches($packaged))->toBeFalse();
});

it('intersects additional only constraints', function (): void {
    $selection = DiagnosticSelection::make(only: ['testing'])
        ->constrain(only: ['PassingDiagnostic']);

    expect($selection->matches(new PassingDiagnostic()))->toBeTrue()
        ->and($selection->matches(new PackagedDiagnostic()))->toBeFalse();
});

it('stacks additional exclusions', function (): void {
    $selection = DiagnosticSelection::make(except: ['testing'])
        ->constrain(except: ['database']);

    expect($selection->matches(new PassingDiagnostic()))->toBeFalse()
        ->and($selection->matches(new DatabaseDiagnostic()))->toBeFalse();
});
