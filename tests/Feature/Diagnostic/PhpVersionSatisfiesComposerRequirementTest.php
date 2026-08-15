<?php
declare(strict_types=1);

use Crustum\Audit\Diagnostic\PhpVersionSatisfiesComposerRequirement;
use Crustum\Audit\Result\Status;
use Crustum\Audit\Support\ComposerJson;

it('reports when the PHP version does not satisfy Composer', function (): void {
    $path = audit_environment_path() . DS . 'composer.json';
    file_put_contents($path, json_encode([
        'require' => ['php' => '<1.0'],
    ], JSON_THROW_ON_ERROR));

    expect((new PhpVersionSatisfiesComposerRequirement(new ComposerJson($path)))->check()->status)
        ->toBe(Status::Fail);
});

it('passes when the PHP version satisfies Composer', function (): void {
    $path = audit_environment_path() . DS . 'composer.json';
    file_put_contents($path, json_encode([
        'require' => ['php' => '>=7.0'],
    ], JSON_THROW_ON_ERROR));

    expect((new PhpVersionSatisfiesComposerRequirement(new ComposerJson($path)))->check()->status)
        ->toBe(Status::Pass);
});
