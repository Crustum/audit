<?php
declare(strict_types=1);

use Crustum\Audit\EnvironmentMode;
use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\FixResult;
use Crustum\Audit\Result\Status;

it('creates diagnostic results for every status', function (): void {
    expect(DiagnosticResult::pass()->status)->toBe(Status::Pass)
        ->and(DiagnosticResult::notice('Notice')->status)->toBe(Status::Notice)
        ->and(DiagnosticResult::warn('Warning')->status)->toBe(Status::Warn)
        ->and(DiagnosticResult::fail('Failure')->status)->toBe(Status::Fail)
        ->and(DiagnosticResult::skip('Skipped')->status)->toBe(Status::Skip)
        ->and(DiagnosticResult::error('Error')->status)->toBe(Status::Error);
});

it('creates fix results for every status', function (): void {
    expect(FixResult::pass()->status)->toBe(Status::Pass)
        ->and(FixResult::notice('Notice')->status)->toBe(Status::Notice)
        ->and(FixResult::warn('Warning')->status)->toBe(Status::Warn)
        ->and(FixResult::fail('Failure')->status)->toBe(Status::Fail)
        ->and(FixResult::skip('Skipped')->status)->toBe(Status::Skip)
        ->and(FixResult::error('Error')->status)->toBe(Status::Error);
});

it('stores diagnostic result guidance and context fluently', function (): void {
    $result = DiagnosticResult::fail('Cache failed', 'cache.failed')
        ->withDetails('Connection refused')
        ->suggest('Check the cache configuration')
        ->confirmUsing('Repair the cache configuration?')
        ->fixOptions(['file' => 'Use file cache'], 'Keep current configuration')
        ->withContext('config', 'default');

    expect($result->details)->toBe('Connection refused')
        ->and($result->remediation)->toBe('Check the cache configuration')
        ->and($result->confirmation)->toBe('Repair the cache configuration?')
        ->and($result->fixOptions)->toBe(['file' => 'Use file cache'])
        ->and($result->fixDeclineLabel)->toBe('Keep current configuration')
        ->and($result->context)->toBe(['config' => 'default']);
});

it('limits fixes to declared environment modes', function (): void {
    $localResult = DiagnosticResult::fail('Failure')->fixable(EnvironmentMode::Local);
    $unrestrictedResult = DiagnosticResult::fail('Failure')->fixable();

    expect($localResult->fixableIn(EnvironmentMode::Local))->toBeTrue()
        ->and($localResult->fixableIn(EnvironmentMode::Production))->toBeFalse()
        ->and($unrestrictedResult->fixableIn(EnvironmentMode::Production))->toBeTrue()
        ->and(DiagnosticResult::fail('Failure')->fixableIn(EnvironmentMode::Local))->toBeFalse();
});

it('identifies failure statuses', function (): void {
    expect(Status::Fail->failed())->toBeTrue()
        ->and(Status::Error->failed())->toBeTrue()
        ->and(Status::Warn->failed())->toBeFalse();
});
