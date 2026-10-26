<?php
declare(strict_types=1);

use Cake\Console\Arguments;
use Cake\Console\CommandInterface;
use Cake\Console\ConsoleIo;
use Cake\View\Exception\MissingTemplateException;
use Crustum\Audit\Command\DiagnosticCommand;
use Crustum\Audit\Result\Status;
use TestApp\Diagnostic\GeneratedFixableDiagnostic;
use TestApp\Diagnostic\GeneratedStandardDiagnostic;

afterEach(function (): void {
    $path = APP . 'Diagnostic';
    if (!is_dir($path)) {
        return;
    }

    foreach (glob($path . DS . '*.php') ?: [] as $file) {
        unlink($file);
    }
});

it('requires a diagnostic name', function (): void {
    $command = new DiagnosticCommand();
    $io = $this->createMock(ConsoleIo::class);
    $io->expects($this->once())
        ->method('err')
        ->with($this->stringContains('You must provide a diagnostic name'));
    $io->expects($this->once())->method('out');

    $args = $this->createMock(Arguments::class);
    $args->expects($this->once())->method('getArgumentAt')->with(0)->willReturn(null);

    expect(executeCommand($command, $args, $io))->toBe(CommandInterface::CODE_ERROR);
});

it('generates a standard diagnostic class', function (): void {
    $command = new DiagnosticCommand();
    $command->plugin = 'Crustum/Audit';

    $io = $this->createMock(ConsoleIo::class);
    $args = $this->createMock(Arguments::class);
    $args->method('getOption')->willReturn(null);
    $command->setArgs($args);
    $command->setIo($io);

    try {
        $content = $command->getContent('HorizonIsRunning', false);
    } catch (MissingTemplateException $missingTemplateException) {
        $this->markTestSkipped('Template not available: ' . $missingTemplateException->getMessage());
    }

    expect($content)
        ->toBeString()
        ->toContain('namespace Crustum\\Audit\\Diagnostic;')
        ->toContain('class HorizonIsRunning extends Diagnostic')
        ->toContain('public function check(): DiagnosticResult')
        ->not->toContain('FixableInterface');
});

it('generates a fixable diagnostic class', function (): void {
    $command = new DiagnosticCommand();
    $command->plugin = 'Crustum/Audit';

    $io = $this->createMock(ConsoleIo::class);
    $args = $this->createMock(Arguments::class);
    $args->method('getOption')->willReturn(null);
    $command->setArgs($args);
    $command->setIo($io);

    try {
        $content = $command->getContent('HorizonIsRunning', true);
    } catch (MissingTemplateException $missingTemplateException) {
        $this->markTestSkipped('Template not available: ' . $missingTemplateException->getMessage());
    }

    expect($content)
        ->toBeString()
        ->toContain('use Crustum\\Audit\\Contract\\FixableInterface;')
        ->toContain('class HorizonIsRunning extends Diagnostic implements FixableInterface')
        ->toContain('->fixable()')
        ->toContain('public function fix(DiagnosticResult $result, ?string $option = null): FixResult');
});

it('generates a diagnostic that runs', function (): void {
    $command = new DiagnosticCommand();
    $io = $this->createMock(ConsoleIo::class);
    $io->method('out');
    $io->method('createFile')->willReturnCallback(function (string $path, string $content): bool {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, $content);

        return true;
    });

    $args = $this->createMock(Arguments::class);
    $args->expects($this->once())->method('getArgumentAt')->with(0)->willReturn('GeneratedStandardDiagnostic');
    $args->method('getOption')->willReturnCallback(fn(string $key): ?bool => match ($key) {
        'fixable' => false,
        'force' => true,
        'verbose' => false,
        default => null,
    });

    expect(executeCommand($command, $args, $io))->toBe(CommandInterface::CODE_SUCCESS);

    $path = APP . 'Diagnostic' . DS . 'GeneratedStandardDiagnostic.php';
    expect(is_file($path))->toBeTrue();

    require_once $path;
    $diagnostic = new GeneratedStandardDiagnostic();

    expect($diagnostic->name)->toBe('Generated standard diagnostic')
        ->and($diagnostic->group)->toBe('generated-standard-diagnostic')
        ->and($diagnostic->check()->status)->toBe(Status::Pass);
});

it('generates a fixable diagnostic that runs and fixes', function (): void {
    $command = new DiagnosticCommand();
    $io = $this->createMock(ConsoleIo::class);
    $io->method('out');
    $io->method('createFile')->willReturnCallback(function (string $path, string $content): bool {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, $content);

        return true;
    });

    $args = $this->createMock(Arguments::class);
    $args->expects($this->once())->method('getArgumentAt')->with(0)->willReturn('GeneratedFixableDiagnostic');
    $args->method('getOption')->willReturnCallback(fn(string $key): ?bool => match ($key) {
        'fixable' => true,
        'force' => true,
        'verbose' => false,
        default => null,
    });

    expect(executeCommand($command, $args, $io))->toBe(CommandInterface::CODE_SUCCESS);

    $path = APP . 'Diagnostic' . DS . 'GeneratedFixableDiagnostic.php';
    require_once $path;
    $diagnostic = new GeneratedFixableDiagnostic();
    $result = $diagnostic->check();

    expect($result->status)->toBe(Status::Fail)
        ->and($result->fixable)->toBeTrue()
        ->and($result->confirmation)->toBe('Apply the fix?')
        ->and($diagnostic->fix($result)->status)->toBe(Status::Pass);
});
