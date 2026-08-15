<?php
declare(strict_types=1);

use Cake\Console\Arguments;
use Cake\Console\CommandInterface;
use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\Core\Container;
use Crustum\Audit\AuditManager;
use Crustum\Audit\AuditPlugin;
use Crustum\Audit\Command\AuditCommand;
use Crustum\Audit\Diagnostic\ApplicationSecretIsSet;
use Crustum\Audit\Diagnostic\ApplicationTimezoneIsValid;
use Crustum\Audit\Diagnostic\EnvironmentFileExists;
use Crustum\Audit\Diagnostic\MigrationsAreUpToDate;
use Crustum\Audit\Diagnostic\PhpVersionSatisfiesComposerRequirement;
use Crustum\Audit\Diagnostic\QueueConnectionIsReachable;
use Crustum\Audit\Test\Fixtures\Diagnostic\FixableDiagnostic;
use Crustum\Audit\Test\Fixtures\Diagnostic\LinkedDiagnostic;
use Crustum\Audit\Test\Fixtures\Diagnostic\PassingDiagnostic;
use Crustum\Audit\Test\Fixtures\Diagnostic\WarningDiagnostic;

afterEach(function (): void {
    Configure::delete('Audit.only');
    Configure::delete('Audit.except');
    Configure::delete('App.defaultTimezone');
    putenv('AI_AGENT');
    unset($_ENV['AI_AGENT'], $_SERVER['AI_AGENT']);
});

it('loads default audit configuration keys', function (): void {
    if (!Configure::check('Audit.environments')) {
        Configure::load('Crustum/Audit.audit', 'default', false);
    }

    expect(Configure::read('Audit.requiredConfiguration'))->toBeArray()
        ->and(Configure::read('Audit.only'))->toBeArray()
        ->and(Configure::read('Audit.except'))->toBeArray()
        ->and(Configure::read('Audit.environments'))->toBeArray();
});

it('registers default diagnostics through the plugin', function (): void {
    $plugin = new AuditPlugin(['path' => dirname(__DIR__, 3) . DS]);
    $registered = $plugin->defaultDiagnostics();

    expect($registered)->toContain(EnvironmentFileExists::class)
        ->and($registered)->toContain(ApplicationSecretIsSet::class)
        ->and($registered)->toContain(MigrationsAreUpToDate::class)
        ->and($registered)->toContain(QueueConnectionIsReachable::class);
});

it('resolves diagnostics that require constructor services', function (): void {
    $container = new Container();
    $plugin = new AuditPlugin(['path' => dirname(__DIR__, 3) . DS]);
    $plugin->services($container);

    $diagnostic = $container->get(PhpVersionSatisfiesComposerRequirement::class);
    $manager = $container->get(AuditManager::class);

    expect($diagnostic)->toBeInstanceOf(PhpVersionSatisfiesComposerRequirement::class)
        ->and($manager)->toBeInstanceOf(AuditManager::class);
});

it('rejects invalid fail-on values before running diagnostics', function (): void {
    $manager = auditManager([LinkedDiagnostic::class]);
    $command = new AuditCommand($manager);
    $io = $this->createMock(ConsoleIo::class);
    $io->expects($this->once())
        ->method('error')
        ->with($this->stringContains('The --fail-on option must be one of'));

    $args = new Arguments([], [
        'format' => 'json',
        'fail-on' => 'broken',
        'only' => 'LinkedDiagnostic',
        'except' => '',
        'bail' => false,
        'fix' => false,
    ], []);

    expect($command->execute($args, $io))->toBe(CommandInterface::CODE_ERROR);
});

it('rejects fixes with json output', function (): void {
    $manager = auditManager([FixableDiagnostic::class]);
    $command = new AuditCommand($manager);
    $io = $this->createMock(ConsoleIo::class);
    $io->expects($this->once())
        ->method('error')
        ->with($this->stringContains('The --fix option may only be used with the cli or agent'));

    $args = new Arguments([], [
        'format' => 'json',
        'fail-on' => 'fail',
        'only' => 'FixableDiagnostic',
        'except' => '',
        'bail' => false,
        'fix' => true,
    ], []);

    expect($command->execute($args, $io))->toBe(CommandInterface::CODE_ERROR);
});

it('renders diagnostic links in json output', function (): void {
    $manager = auditManager([LinkedDiagnostic::class]);
    $command = new AuditCommand($manager);
    $buffer = '';
    $io = $this->createMock(ConsoleIo::class);
    $io->method('out')->willReturnCallback(function (string $message) use (&$buffer): void {
        $buffer .= $message;
    });

    $args = new Arguments([], [
        'format' => 'json',
        'fail-on' => 'never',
        'only' => 'LinkedDiagnostic',
        'except' => '',
        'bail' => false,
        'fix' => false,
    ], []);

    expect($command->execute($args, $io))->toBe(CommandInterface::CODE_SUCCESS);

    $payload = json_decode($buffer, true, flags: JSON_THROW_ON_ERROR);
    expect($payload['diagnostics'][0]['links'])->toBe([
        'Related cache guide' => 'https://example.com/cache',
    ]);
});

it('renders github annotations', function (): void {
    $manager = auditManager([LinkedDiagnostic::class]);
    $command = new AuditCommand($manager);
    $lines = [];
    $io = $this->createMock(ConsoleIo::class);
    $io->method('out')->willReturnCallback(function (string $message) use (&$lines): void {
        $lines[] = $message;
    });

    $args = new Arguments([], [
        'format' => 'github',
        'fail-on' => 'never',
        'only' => 'LinkedDiagnostic',
        'except' => '',
        'bail' => false,
        'fix' => false,
    ], []);

    expect($command->execute($args, $io))->toBe(CommandInterface::CODE_SUCCESS)
        ->and(implode("\n", $lines))
        ->toContain('title=Testing diagnostic has links')
        ->toContain('https://example.com/cache');
});

it('renders agent output', function (): void {
    $manager = auditManager([PassingDiagnostic::class]);
    $command = new AuditCommand($manager);
    $buffer = '';
    $io = $this->createMock(ConsoleIo::class);
    $io->method('out')->willReturnCallback(function (string $message) use (&$buffer): void {
        $buffer .= $message;
    });

    $args = new Arguments([], [
        'format' => 'agent',
        'fail-on' => 'never',
        'only' => 'PassingDiagnostic',
        'except' => '',
        'bail' => false,
        'fix' => false,
    ], []);

    expect($command->execute($args, $io))->toBe(CommandInterface::CODE_SUCCESS);

    $payload = json_decode(trim($buffer), true, flags: JSON_THROW_ON_ERROR);
    expect($payload)->toMatchArray([
        'tool' => 'audit',
        'result' => 'passed',
        'diagnostics' => 1,
        'passed' => 1,
    ]);
});

it('bails after the first failure from the command', function (): void {
    $manager = auditManager([
        WarningDiagnostic::class,
        FixableDiagnostic::class,
        PassingDiagnostic::class,
    ]);
    $command = new AuditCommand($manager);
    $buffer = '';
    $io = $this->createMock(ConsoleIo::class);
    $io->method('out')->willReturnCallback(function (string $message) use (&$buffer): void {
        $buffer .= $message;
    });

    $args = new Arguments([], [
        'format' => 'json',
        'fail-on' => 'fail',
        'only' => 'WarningDiagnostic,FixableDiagnostic,PassingDiagnostic',
        'except' => '',
        'bail' => true,
        'fix' => false,
    ], []);

    expect($command->execute($args, $io))->toBe(CommandInterface::CODE_ERROR);

    $payload = json_decode($buffer, true, flags: JSON_THROW_ON_ERROR);
    expect(array_column($payload['diagnostics'], 'class'))->toBe([
        WarningDiagnostic::class,
        FixableDiagnostic::class,
    ]);
});

it('applies configured only selectors', function (): void {
    Configure::write('Audit.only', ['PassingDiagnostic']);
    $container = new Container();
    $container->addShared(PassingDiagnostic::class);
    $container->addShared(WarningDiagnostic::class);

    $manager = (new AuditManager($container))->diagnostics([
        PassingDiagnostic::class,
        WarningDiagnostic::class,
    ]);

    $command = new AuditCommand($manager);
    $buffer = '';
    $io = $this->createMock(ConsoleIo::class);
    $io->method('out')->willReturnCallback(function (string $message) use (&$buffer): void {
        $buffer .= $message;
    });

    $args = new Arguments([], [
        'format' => 'json',
        'fail-on' => 'never',
        'only' => '',
        'except' => '',
        'bail' => false,
        'fix' => false,
    ], []);

    expect($command->execute($args, $io))->toBe(CommandInterface::CODE_SUCCESS);

    $payload = json_decode($buffer, true, flags: JSON_THROW_ON_ERROR);
    expect($payload['diagnostics'])->toHaveCount(1)
        ->and($payload['diagnostics'][0]['class'])->toBe(PassingDiagnostic::class);
});

it('restores a valid timezone before termination', function (): void {
    Configure::write('App.defaultTimezone', 'Invalid/Timezone');
    $manager = auditManager([ApplicationTimezoneIsValid::class]);
    $command = new AuditCommand($manager);
    $io = $this->createMock(ConsoleIo::class);
    $io->method('out');

    $args = new Arguments([], [
        'format' => 'json',
        'fail-on' => 'never',
        'only' => 'ApplicationTimezoneIsValid',
        'except' => '',
        'bail' => false,
        'fix' => false,
    ], []);

    $command->execute($args, $io);

    expect(Configure::read('App.defaultTimezone'))->toBe(date_default_timezone_get());
});
