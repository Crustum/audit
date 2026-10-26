<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Crustum\Audit\Diagnostic\SessionConfigIsReachable;
use Crustum\Audit\Result\Status;

it('validates configured session backends', function (): void {
    Configure::write('Session', ['handler' => ['className' => 'Cache', 'config' => 'missing']]);
    expect(new SessionConfigIsReachable()->check()->status)->toBe(Status::Fail);

    Configure::write('Session', ['defaults' => 'php']);
    expect(new SessionConfigIsReachable()->check()->status)->toBe(Status::Pass);
});
