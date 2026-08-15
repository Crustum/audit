<?php
declare(strict_types=1);

namespace Crustum\Audit\Support;

/**
 * Reads Composer metadata for the audited application.
 */
class ComposerJson
{
    /**
     * Decoded composer.json payload.
     *
     * @var array<string, mixed>|null
     */
    private ?array $contents = null;

    /**
     * Whether the file has already been read.
     *
     * @var bool
     */
    private bool $loaded = false;

    /**
     * Composer file path.
     *
     * @var string
     */
    private readonly string $path;

    /**
     * Constructor.
     *
     * @param string|null $path Optional composer.json path.
     */
    public function __construct(?string $path = null)
    {
        $this->path = $path ?? ROOT . DS . 'composer.json';
    }

    /**
     * Determine whether composer.json exists.
     *
     * @return bool
     */
    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * Get the required PHP version constraint.
     *
     * @return string|null
     */
    public function phpConstraint(): ?string
    {
        $require = $this->contents()['require'] ?? null;
        $constraint = is_array($require) ? ($require['php'] ?? null) : null;

        return is_string($constraint) && $constraint !== '' ? $constraint : null;
    }

    /**
     * Get Composer-required PHP extensions.
     *
     * @return list<string>
     */
    public function requiredExtensions(): array
    {
        $contents = $this->contents() ?? [];

        return $this->extensionsFrom([
            ...$this->packageNames($contents['require'] ?? []),
            ...$this->packageNames($contents['require-dev'] ?? []),
        ]);
    }

    /**
     * Get Composer-suggested PHP extensions.
     *
     * @return list<string>
     */
    public function suggestedExtensions(): array
    {
        return $this->extensionsFrom(
            $this->packageNames($this->contents()['suggest'] ?? []),
        );
    }

    /**
     * Get decoded composer.json contents.
     *
     * @return array<string, mixed>|null
     */
    public function contents(): ?array
    {
        if ($this->loaded) {
            return $this->contents;
        }

        $this->loaded = true;

        if (!$this->exists()) {
            return null;
        }

        $encoded = file_get_contents($this->path);

        if ($encoded === false) {
            return null;
        }

        $decoded = json_decode($encoded, true);

        if (!is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $this->contents = $decoded;
    }

    /**
     * Get package names from a Composer object.
     *
     * @param mixed $packages Composer object value.
     * @return list<string>
     */
    private function packageNames(mixed $packages): array
    {
        if (!is_array($packages)) {
            return [];
        }

        return array_values(array_filter(array_keys($packages), is_string(...)));
    }

    /**
     * Get extension names from Composer package names.
     *
     * @param list<string> $packages Composer package names.
     * @return list<string>
     */
    private function extensionsFrom(array $packages): array
    {
        return array_values(array_unique(array_map(
            static fn(string $package): string => substr($package, 4),
            array_filter(
                $packages,
                static fn(string $package): bool => str_starts_with($package, 'ext-'),
            ),
        )));
    }
}
