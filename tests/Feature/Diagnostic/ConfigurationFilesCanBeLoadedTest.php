<?php
declare(strict_types=1);

use Crustum\Audit\Diagnostic\ConfigurationFilesCanBeLoaded;
use Crustum\Audit\Result\Status;

it('loads valid configuration files and reports broken files', function (): void {
    $path = audit_cake_core_path() . DS;
    file_put_contents($path . 'valid.php', '<?php return ["valid" => true];');
    file_put_contents($path . 'paths.php', '<?php define("AUDIT_SHOULD_NOT_LOAD", true);');
    $diagnostic = new ConfigurationFilesCanBeLoaded($path);
    expect($diagnostic->check()->status)->toBe(Status::Pass)
        ->and(defined('AUDIT_SHOULD_NOT_LOAD'))->toBeFalse();

    file_put_contents($path . 'broken.php', '<?php throw new RuntimeException("broken config");');
    expect($diagnostic->check()->status)->toBe(Status::Fail)
        ->and($diagnostic->check()->details)->toContain('broken config');
});

it('skips configuration loading when no config files are present', function (): void {
    $path = audit_cake_core_path() . DS;

    expect(new ConfigurationFilesCanBeLoaded($path)->check()->status)->toBe(Status::Skip);
});
