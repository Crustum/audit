<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Audit\Support\Configured;

afterEach(function (): void {
    Configure::delete('Services.example');
});

it('returns configured string values', function (): void {
    Configure::write('Services.example.key', 'secret');

    expect(Configured::string('Services.example.key'))->toBe('secret');
});

it('falls back for blank or non-string values', function (): void {
    Configure::write('Services.example', [
        'empty' => '',
        'array' => ['nested'],
    ]);

    expect(Configured::string('Services.example.empty'))->toBeNull()
        ->and(Configured::string('Services.example.array'))->toBeNull()
        ->and(Configured::string('Services.example.absent', 'default'))->toBe('default');
});

it('reports configuration keys without usable values', function (): void {
    Configure::write('Services.example', [
        'null' => null,
        'blank' => '  ',
        'emptyArray' => [],
    ]);

    expect(Configured::missing([
        'Services.example.null',
        'Services.example.blank',
        'Services.example.emptyArray',
        'Services.example.absent',
    ]))->toBe([
        'Services.example.null',
        'Services.example.blank',
        'Services.example.emptyArray',
        'Services.example.absent',
    ]);
});

it('keeps false zero and populated arrays as usable values', function (): void {
    Configure::write('Services.example', [
        'string' => 'value',
        'false' => false,
        'zero' => 0,
        'array' => ['nested'],
    ]);

    expect(Configured::missing([
        'Services.example.string',
        'Services.example.false',
        'Services.example.zero',
        'Services.example.array',
    ]))->toBe([]);
});
