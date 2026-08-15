<?php
declare(strict_types=1);

use Crustum\Audit\Result\DiagnosticResult;
use Crustum\Audit\Result\Link;
use Crustum\Audit\Result\Message;

it('creates CakePHP documentation links', function (): void {
    $link = Link::docs('core-libraries/caching');

    expect($link->label)->toBe('Caching documentation')
        ->and($link->url)->toBe('https://book.cakephp.org/5/en/core-libraries/caching.html')
        ->and($link->agentUrl)->toBe('https://book.cakephp.org/5/en/core-libraries/caching.html');
});

it('creates section links with custom labels', function (): void {
    $link = Link::docs('core-libraries/caching', 'configuring-cache-class', 'Cache configuration');

    expect($link->label)->toBe('Cache configuration')
        ->and($link->url)
        ->toBe('https://book.cakephp.org/5/en/core-libraries/caching.html#configuring-cache-class')
        ->and($link->agentUrl)
        ->toBe('https://book.cakephp.org/5/en/core-libraries/caching.html');
});

it('uses arbitrary URLs for both people and agents', function (): void {
    $link = Link::to('PHP timezone list', 'https://www.php.net/manual/en/timezones.php');

    expect($link->label)->toBe('PHP timezone list')
        ->and($link->url)->toBe('https://www.php.net/manual/en/timezones.php')
        ->and($link->agentUrl)->toBe('https://www.php.net/manual/en/timezones.php');
});

it('replaces tokens across every link field', function (): void {
    $link = Link::to('{service} documentation', 'https://example.com/{service}')
        ->replace(['{service}' => 'cache']);

    expect($link)->toEqual(Link::to('cache documentation', 'https://example.com/cache'));
});

it('attaches links to diagnostic results', function (): void {
    $link = Link::docs('core-libraries/caching');
    $result = DiagnosticResult::warn('Cache warning')->link($link);

    expect($result->links)->toBe([$link]);
});

it('attaches links fluently to message definitions', function (): void {
    $link = Link::docs('core-libraries/caching');
    $message = Message::make('Cache warning')->link($link);

    expect($message->links)->toBe([$link]);
});
