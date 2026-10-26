<?php
declare(strict_types=1);

use Crustum\Audit\Diagnostic\RequiredPhpExtensionsAreLoaded;
use Crustum\Audit\Result\Status;
use Crustum\Audit\Support\ComposerJson;

it('reports missing required PHP extensions from Composer', function (): void {
    $path = audit_environment_path() . DS . 'composer.json';
    file_put_contents($path, json_encode([
        'require' => ['ext-audit_missing_required' => '*'],
    ], JSON_THROW_ON_ERROR));

    expect(new RequiredPhpExtensionsAreLoaded(new ComposerJson($path))->check()->status)
        ->toBe(Status::Fail);
});

it('passes when required PHP extensions are loaded', function (): void {
    $path = audit_environment_path() . DS . 'composer.json';
    file_put_contents($path, json_encode([
        'require' => ['ext-json' => '*'],
    ], JSON_THROW_ON_ERROR));

    expect(new RequiredPhpExtensionsAreLoaded(new ComposerJson($path))->check()->status)
        ->toBe(Status::Pass);
});
