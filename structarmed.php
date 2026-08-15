<?php
declare(strict_types=1);

use Boundwize\StructArmed\Architecture;

return Architecture::define()
    ->layerPattern('Foundation', [
        '/^Crustum\\\\Audit\\\\(Support|Contract)(\\\\.*)?$/',
        '/^Crustum\\\\Audit\\\\EnvironmentMode$/',
        '/^Crustum\\\\Audit\\\\Result\\\\(DiagnosticResult|FixResult|Status|Link)$/',
    ])
    ->layerPattern('Result', '/^Crustum\\\\Audit\\\\Result\\\\.*$/')
    ->layerPattern('Diagnostic', [
        '/^Crustum\\\\Audit\\\\Diagnostic(\\\\.*)?$/',
        '/^Crustum\\\\Audit\\\\(DiagnosticSource|DiagnosticSelection)$/',
    ])
    ->layerPattern('Renderer', '/^Crustum\\\\Audit\\\\Renderer\\\\.*$/')
    ->layerPattern('Plugin', [
        '/^Crustum\\\\Audit\\\\(AuditPlugin|AuditManager|PendingRun)$/',
        '/^Crustum\\\\Audit\\\\Command\\\\.*$/',
    ])
    ->ruleset([
        'Foundation' => [],
        'Result' => ['Foundation'],
        'Diagnostic' => ['Result', 'Foundation'],
        'Renderer' => ['Result', 'Foundation'],
        'Plugin' => ['+Diagnostic', '+Renderer'],
    ]);
