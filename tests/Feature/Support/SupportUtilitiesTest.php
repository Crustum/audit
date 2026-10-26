<?php
declare(strict_types=1);

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Crustum\Audit\DiagnosticSource;
use Crustum\Audit\Support\ActiveDrivers;
use Crustum\Audit\Support\Composer;
use Crustum\Audit\Support\ComposerJson;
use Crustum\Audit\Support\Configured;
use Crustum\Audit\Support\Details;

it('formats diagnostic details', function (): void {
    expect(Details::bullets(['first', '- second']))->toBe('- first' . PHP_EOL . '- second')
        ->and(Details::failures(['cache' => 'unreachable']))
        ->toBe('- cache: unreachable')
        ->and(Details::processOutput('output', 'error', 'fallback'))->toBe('error')
        ->and(Details::processOutput(' ', '', 'fallback'))->toBe('fallback');
});

it('reads Composer metadata defensively', function (): void {
    $path = TMP . 'audit-composer-json-test.json';
    file_put_contents($path, json_encode([
        'require' => [
            'php' => '>=8.2',
            'ext-json' => '*',
        ],
        'require-dev' => [
            'ext-mbstring' => '*',
        ],
        'suggest' => [
            'ext-redis' => 'Redis support',
        ],
    ], JSON_THROW_ON_ERROR));

    try {
        $composerJson = new ComposerJson($path);

        expect($composerJson->exists())->toBeTrue()
            ->and($composerJson->phpConstraint())->toBe('>=8.2')
            ->and($composerJson->requiredExtensions())->toBe(['json', 'mbstring'])
            ->and($composerJson->suggestedExtensions())->toBe(['redis']);
    } finally {
        unlink($path);
    }
});

it('returns null metadata for missing or invalid Composer files', function (): void {
    $path = TMP . 'audit-invalid-composer-json-test.json';
    file_put_contents($path, '{');

    try {
        expect(new ComposerJson(TMP . 'missing-composer.json')->contents())->toBeNull()
            ->and(new ComposerJson($path)->contents())->toBeNull();
    } finally {
        unlink($path);
    }
});

it('creates bounded Composer processes', function (): void {
    $composer = new Composer(['composer-test'], ROOT);
    $process = $composer->process(['validate'], 15.0);

    expect($composer->command())->toBe(['composer-test'])
        ->and($process->getCommandLine())->toContain('composer-test')
        ->and($process->getCommandLine())->toContain('validate')
        ->and($process->getTimeout())->toBe(15.0)
        ->and($process->getWorkingDirectory())->toBe(ROOT);
});

it('discovers active CakePHP infrastructure configurations', function (): void {
    Configure::write('Session.handler.className', 'Database');

    try {
        expect(ActiveDrivers::cacheConfigurations())->toContain(...Cache::configured())
            ->and(ActiveDrivers::databaseConnections())->toContain('test')
            ->and(ActiveDrivers::sessionHandler())->toBe('Database');
    } finally {
        Configure::delete('Session.handler');
    }
});

it('resolves Composer source metadata', function (): void {
    $pluginSource = DiagnosticSource::resolve(Configured::class);
    $frameworkSource = DiagnosticSource::resolve(Cache::class);

    expect($pluginSource->package)->toBe('crustum/audit')
        ->and($pluginSource->relativeFile)->toBe('src' . DIRECTORY_SEPARATOR . 'Support' . DIRECTORY_SEPARATOR . 'Configured.php')
        ->and($pluginSource->application)->toBeTrue()
        ->and($frameworkSource->package)->toBe('cakephp/cakephp')
        ->and($frameworkSource->application)->toBeFalse();
});
