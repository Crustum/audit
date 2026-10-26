<?php
declare(strict_types=1);

use Crustum\Audit\Diagnostic\StorageIsWritable;
use Crustum\Audit\Result\Status;

it('creates missing CakePHP runtime directories', function (): void {
    $path = audit_cake_core_path();
    $paths = [
        'tmp/' => $path . DS . 'tmp',
        'cache/' => $path . DS . 'tmp' . DS . 'cache',
        'logs/' => $path . DS . 'logs',
    ];
    $diagnostic = new StorageIsWritable($paths);
    $result = $diagnostic->check();

    expect($result->status)->toBe(Status::Fail)
        ->and($diagnostic->fix($result)->status)->toBe(Status::Pass)
        ->and($diagnostic->check()->status)->toBe(Status::Pass);
});

it('passes when CakePHP runtime directories are already writable', function (): void {
    $path = audit_cake_core_path();
    $paths = [
        'tmp/' => $path . DS . 'tmp',
        'logs/' => $path . DS . 'logs',
    ];
    foreach ($paths as $dir) {
        mkdir($dir, 0775, true);
    }

    expect(new StorageIsWritable($paths)->check()->status)->toBe(Status::Pass);
});
