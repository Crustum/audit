<?php
declare(strict_types=1);

use Crustum\Audit\Diagnostic\RecommendedPhpExtensionsAreLoaded;
use Crustum\Audit\Result\Status;
use Crustum\Audit\Support\ComposerJson;

it('warns about missing recommended PHP extensions from Composer', function (): void {
    $path = audit_environment_path() . DS . 'composer.json';
    file_put_contents($path, json_encode([
        'suggest' => ['ext-audit_missing_suggested' => 'Optional'],
    ], JSON_THROW_ON_ERROR));

    expect(new RecommendedPhpExtensionsAreLoaded(new ComposerJson($path))->check()->status)
        ->toBe(Status::Warn);
});

it('passes when recommended PHP extensions are loaded', function (): void {
    $path = audit_environment_path() . DS . 'composer.json';
    file_put_contents($path, json_encode([
        'suggest' => ['ext-json' => 'Already loaded'],
    ], JSON_THROW_ON_ERROR));

    expect(new RecommendedPhpExtensionsAreLoaded(new ComposerJson($path))->check()->status)
        ->toBe(Status::Pass);
});
