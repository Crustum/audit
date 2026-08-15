<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Audit\Contract\FixableInterface;
use Crustum\Audit\Diagnostic;
use Crustum\Audit\DiagnosticSource;
use Crustum\Audit\Result\DiagnosticFixOutcome;
use Crustum\Audit\Result\DiagnosticOutcome;
use Crustum\Audit\Result\DiagnosticReport;
use Crustum\Audit\Result\FixResult;
use Crustum\Audit\Test\Fixtures\Diagnostic\DatabaseDiagnostic;
use Crustum\Audit\Test\Fixtures\Diagnostic\DefaultMetadataDiagnostic;
use Crustum\Audit\Test\Fixtures\Diagnostic\FixableDiagnostic;
use Crustum\Audit\Test\Fixtures\Diagnostic\LinkedDiagnostic;
use Crustum\Audit\Test\Fixtures\Diagnostic\LocalOnlyFixableDiagnostic;
use Crustum\Audit\Test\Fixtures\Diagnostic\OptionFixableDiagnostic;
use Crustum\Audit\Test\Fixtures\Diagnostic\PackagedDiagnostic;
use Crustum\Audit\Test\Fixtures\Diagnostic\PassingDiagnostic;
use Crustum\Audit\Test\Fixtures\Diagnostic\SharedStateDiagnostic;
use Crustum\Audit\Test\Fixtures\Diagnostic\ThrowingDiagnostic;
use Crustum\Audit\Test\Fixtures\Diagnostic\ThrowingFixDiagnostic;
use Crustum\Audit\Test\Fixtures\Diagnostic\WarningDiagnostic;

afterEach(function (): void {
    Configure::delete('Audit.only');
    Configure::delete('Audit.except');
    Configure::delete('Audit.environment');
    Configure::delete('Audit.environments');
    Configure::delete('AuditTesting');
});

it('registers diagnostics and rejects unrelated classes', function (): void {
    $manager = auditManager([PassingDiagnostic::class]);

    expect($manager->registered())->toBe([PassingDiagnostic::class])
        ->and($manager->hasDiagnostics())->toBeTrue();

    $manager->diagnostic(stdClass::class);
})->throws(InvalidArgumentException::class);

it('resolves diagnostic source overrides', function (): void {
    $fixtureSource = DiagnosticSource::resolve(PassingDiagnostic::class);
    $packagedSource = DiagnosticSource::for(new PackagedDiagnostic());

    expect($fixtureSource->package)->toBe('crustum/audit')
        ->and($fixtureSource->application)->toBeTrue()
        ->and($packagedSource->label())->toBe('vendor/package')
        ->and($packagedSource->application)->toBeFalse();
});

it('runs diagnostics in first-seen group order', function (): void {
    $manager = auditManager([
        PassingDiagnostic::class,
        DatabaseDiagnostic::class,
        WarningDiagnostic::class,
    ]);

    $groups = $manager->selectedByGroup();
    $classes = array_map(
        static fn(DiagnosticOutcome $outcome): string => $outcome->diagnostic::class,
        $manager->run()->diagnostics(),
    );

    expect(array_keys($groups))->toBe(['testing', 'database'])
        ->and($classes)->toBe([
            PassingDiagnostic::class,
            WarningDiagnostic::class,
            DatabaseDiagnostic::class,
        ]);
});

it('defaults diagnostic metadata from the class name', function (): void {
    $diagnostic = new DefaultMetadataDiagnostic();

    expect($diagnostic->name)->toBe('Default metadata diagnostic')
        ->and($diagnostic->group)->toBe('default-metadata-diagnostic');
});

it('converts diagnostic exceptions into error outcomes', function (): void {
    $report = auditManager([ThrowingDiagnostic::class])->run();
    $outcome = $report->diagnostics()[0];

    expect($outcome->result->status->value)->toBe('error')
        ->and($outcome->result->summary)->toBe('The diagnostic exploded.')
        ->and($outcome->result->context['exception'])->toBe(RuntimeException::class)
        ->and($report->hasFailures())->toBeTrue();
});

it('runs plain fixes and reports warnings', function (): void {
    $manager = auditManager([
        WarningDiagnostic::class,
        FixableDiagnostic::class,
    ]);
    $report = $manager->run();
    $outcome = $report->diagnostics()[1];

    expect($report->hasWarnings())->toBeTrue()
        ->and($outcome->diagnostic)->toBeInstanceOf(FixableInterface::class)
        ->and($outcome->fixable())->toBeTrue()
        ->and($manager->fix($outcome)->result->summary)->toBe('The diagnostic was fixed.');
});

it('applies selected fix options and reruns diagnostics', function (): void {
    $rerunning = false;
    $report = auditManager([OptionFixableDiagnostic::class])
        ->fixUsing(static fn(DiagnosticOutcome $outcome): string => 'a')
        ->beforeRerun(function () use (&$rerunning): void {
            $rerunning = true;
        })
        ->run();

    expect($report->fixes())->toHaveCount(1)
        ->and($report->fixes()[0]->result->summary)->toBe('The option diagnostic was fixed with [a].')
        ->and($report->hasFailures())->toBeFalse()
        ->and($rerunning)->toBeTrue();
});

it('declines automatic fixes without rerunning', function (): void {
    $rerunning = false;
    $report = auditManager([FixableDiagnostic::class])
        ->fixUsing(static fn(DiagnosticOutcome $outcome): bool => false)
        ->beforeRerun(function () use (&$rerunning): void {
            $rerunning = true;
        })
        ->run();

    expect($report->fixes())->toBe([])
        ->and($report->hasFailures())->toBeTrue()
        ->and($rerunning)->toBeFalse();
});

it('rejects invalid fix option decisions', function (): void {
    auditManager([OptionFixableDiagnostic::class])
        ->fixUsing(static fn(DiagnosticOutcome $outcome): bool => true)
        ->run();
})->throws(LogicException::class, 'requires a fix option');

it('rejects undeclared fix options', function (): void {
    $manager = auditManager([OptionFixableDiagnostic::class]);
    $outcome = $manager->run()->diagnostics()[0];

    $manager->fix($outcome, 'missing');
})->throws(LogicException::class, 'is not declared');

it('contains fix exceptions in error outcomes', function (): void {
    $manager = auditManager([ThrowingFixDiagnostic::class]);
    $fix = $manager->fix($manager->run()->diagnostics()[0]);

    expect($fix->result->status->value)->toBe('error')
        ->and($fix->result->summary)
        ->toBe('Failed to fix Testing diagnostic fix throws: permission denied');
});

it('enforces environment-scoped fixes', function (): void {
    Configure::write('Audit.environments.local', ['local']);
    Configure::write('Audit.environment', 'production');
    $manager = auditManager([LocalOnlyFixableDiagnostic::class]);
    $productionOutcome = $manager->run()->diagnostics()[0];

    expect($productionOutcome->fixable())->toBeFalse();

    Configure::write('Audit.environment', 'local');

    expect($manager->run()->diagnostics()[0]->fixable())->toBeTrue();
});

it('applies configured selectors to runs', function (): void {
    Configure::write('Audit.only', ['database']);
    $report = auditManager([
        PassingDiagnostic::class,
        DatabaseDiagnostic::class,
    ])->run();

    expect($report->diagnostics())->toHaveCount(1)
        ->and($report->diagnostics()[0]->diagnostic::class)->toBe(DatabaseDiagnostic::class);
});

it('bails after the first failed diagnostic', function (): void {
    $report = auditManager([
        WarningDiagnostic::class,
        FixableDiagnostic::class,
        PassingDiagnostic::class,
        DatabaseDiagnostic::class,
    ])->bail()->run();

    expect(array_map(
        static fn(DiagnosticOutcome $outcome): string => $outcome->diagnostic::class,
        $report->diagnostics(),
    ))->toBe([
        WarningDiagnostic::class,
        FixableDiagnostic::class,
    ]);
});

it('reruns after a shared-state fix', function (): void {
    Configure::write('AuditTesting.sharedStateFixed', false);

    $report = auditManager([SharedStateDiagnostic::class])
        ->fixUsing(static fn(DiagnosticOutcome $outcome): bool => true)
        ->run();

    expect($report->fixes())->toHaveCount(1)
        ->and($report->diagnostics()[0]->result->status->value)->toBe('pass')
        ->and($report->hasFailures())->toBeFalse();
});

it('allows runner-owned progress observation', function (): void {
    $observed = [];
    $report = auditManager([
        PassingDiagnostic::class,
        DatabaseDiagnostic::class,
        ThrowingDiagnostic::class,
    ])
        ->observeUsing(function (string $group, Closure $continue) use (&$observed): void {
            $observed[] = 'group:' . $group;
            $continue(
                before: function (Diagnostic $diagnostic) use (&$observed): void {
                    $observed[] = 'checking:' . $diagnostic->name;
                },
                after: function (DiagnosticOutcome $outcome) use (&$observed): void {
                    $observed[] = 'result:' . $outcome->result->status->value;
                },
            );
        })
        ->run();

    expect($report->diagnostics())->toHaveCount(3)
        ->and($observed)->toBe([
            'group:testing',
            'checking:Testing diagnostic passes',
            'result:pass',
            'checking:Testing diagnostic throws',
            'result:error',
            'group:database',
            'checking:Database connects',
            'result:pass',
        ]);
});

it('requires observers to invoke their continuation', function (): void {
    auditManager([PassingDiagnostic::class])
        ->observeUsing(static function (string $group, Closure $continue): void {
        })
        ->run();
})->throws(LogicException::class, 'must invoke its continuation');

it('builds named messages and serializes reports', function (): void {
    $outcome = auditManager([LinkedDiagnostic::class])->run()->diagnostics()[0];
    $fix = new DiagnosticFixOutcome($outcome->diagnostic, FixResult::pass('Fixed'));
    $report = new DiagnosticReport([$outcome], [$fix]);
    $payload = $report->toArray();

    expect($outcome->result->summary)->toBe('The linked diagnostic warned about cache.')
        ->and($outcome->result->links[0]->url)->toBe('https://example.com/cache')
        ->and($payload['version'])->toBe(1)
        ->and($payload['diagnostics'][0]['status'])->toBe('warn')
        ->and(json_encode($report, JSON_THROW_ON_ERROR))->toContain('"version":1');
});

it('appends fixes immutably', function (): void {
    $diagnostic = new PassingDiagnostic();
    $first = new DiagnosticFixOutcome($diagnostic, FixResult::pass('First'));
    $second = new DiagnosticFixOutcome($diagnostic, FixResult::pass('Second'));
    $report = new DiagnosticReport();

    expect($report->withFixes([$first])->withFixes([$second])->fixes())->toBe([$first, $second]);
});
