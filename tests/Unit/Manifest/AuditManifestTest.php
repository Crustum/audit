<?php
declare(strict_types=1);

use Crustum\Audit\AuditPlugin;
use Crustum\Audit\Manifest\AuditManifest;
use Crustum\PluginManifest\Manifest\ManifestResolver;

it('emits config, bootstrap and star operations', function (): void {
    $tags = array_column(AuditManifest::manifest(), 'tag');

    expect($tags)->toContain('config')
        ->and($tags)->toContain('bootstrap')
        ->and($tags)->toContain('star-repo');
});

it('is discoverable through the resolver', function (): void {
    expect(ManifestResolver::providerClass(AuditPlugin::class))->toBe(AuditManifest::class)
        ->and(ManifestResolver::manifestFor(AuditPlugin::class))->toBe(AuditManifest::manifest());
});

it('leaves the plugin class without a manifest contract', function (): void {
    expect(method_exists(AuditPlugin::class, 'manifest'))->toBeFalse();
});
