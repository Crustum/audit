<?php
declare(strict_types=1);

namespace Crustum\Audit;

use Composer\InstalledVersions;
use ReflectionClass;
use Throwable;

/**
 * Composer source metadata for a diagnostic class.
 */
class DiagnosticSource
{
    /**
     * Resolved sources keyed by class.
     *
     * @var array<class-string, self>
     */
    private static array $sources = [];

    /**
     * Constructor.
     *
     * @param string|null $package Composer package name.
     * @param string|null $file Absolute class file path.
     * @param string|null $relativeFile File path relative to the package.
     * @param bool $application Whether this is an application diagnostic.
     */
    public function __construct(
        public readonly ?string $package,
        public readonly ?string $file,
        public readonly ?string $relativeFile,
        public readonly bool $application,
    ) {
    }

    /**
     * Resolve source metadata for a class.
     *
     * @param class-string $class Class name.
     * @return self
     */
    public static function resolve(string $class): self
    {
        if (!array_key_exists($class, self::$sources)) {
            self::$sources[$class] = self::resolveSource($class);
        }

        return self::$sources[$class];
    }

    /**
     * Resolve source metadata for a diagnostic instance.
     *
     * @param \Crustum\Audit\Diagnostic $diagnostic Diagnostic instance.
     * @return self
     */
    public static function for(Diagnostic $diagnostic): self
    {
        $source = self::resolve($diagnostic::class);
        $package = $diagnostic->package();

        if ($package !== $source->package) {
            return new self(
                package: $package,
                file: $source->file,
                relativeFile: $source->relativeFile,
                application: $package === null,
            );
        }

        return $source;
    }

    /**
     * Get the user-facing source label.
     *
     * @return string
     */
    public function label(): string
    {
        return $this->package ?? 'application';
    }

    /**
     * Resolve source metadata for a class.
     *
     * @param class-string $class Class name.
     * @return self
     */
    protected static function resolveSource(string $class): self
    {
        $file = self::classFile($class);

        if ($file === null) {
            return new self(
                package: null,
                file: null,
                relativeFile: null,
                application: true,
            );
        }

        $package = self::installedPackageForFile($file);
        $packagePath = $package !== null ? self::packagePath($package) : null;

        return new self(
            package: $package,
            file: $file,
            relativeFile: $packagePath !== null ? self::relativePath($packagePath, $file) : null,
            application: self::isApplicationSource($class, $package),
        );
    }

    /**
     * Resolve a class file path.
     *
     * @param class-string $class Class name.
     * @return string|null
     */
    protected static function classFile(string $class): ?string
    {
        try {
            $file = new ReflectionClass($class)->getFileName();
        } catch (Throwable) {
            return null;
        }

        if ($file === false) {
            return null;
        }

        $realPath = realpath($file);

        return $realPath === false ? null : $realPath;
    }

    /**
     * Determine whether a class belongs to the host application.
     *
     * @param class-string $class Class name.
     * @param string|null $package Composer package name.
     * @return bool
     */
    protected static function isApplicationSource(string $class, ?string $package): bool
    {
        if (str_starts_with($class, 'Crustum\\Audit\\Diagnostic\\')) {
            return false;
        }

        $rootPackage = self::rootPackageName();

        return $rootPackage !== null && $package === $rootPackage;
    }

    /**
     * Find the installed Composer package containing a file.
     *
     * @param string $file Absolute file path.
     * @return string|null
     */
    protected static function installedPackageForFile(string $file): ?string
    {
        $match = null;
        $matchLength = -1;

        foreach (InstalledVersions::getInstalledPackages() as $package) {
            try {
                $path = InstalledVersions::getInstallPath($package);
            } catch (Throwable) {
                continue;
            }

            if (!is_string($path)) {
                continue;
            }

            $realPath = realpath($path);

            if ($realPath === false) {
                continue;
            }

            if (!self::contains($realPath, $file)) {
                continue;
            }

            $pathLength = strlen($realPath);

            if ($pathLength > $matchLength) {
                $match = $package;
                $matchLength = $pathLength;
            }
        }

        return $match;
    }

    /**
     * Get an installed package path.
     *
     * @param string $package Composer package name.
     * @return string|null
     */
    protected static function packagePath(string $package): ?string
    {
        try {
            $path = InstalledVersions::getInstallPath($package);
        } catch (Throwable) {
            return null;
        }

        if (!is_string($path)) {
            return null;
        }

        $realPath = realpath($path);

        return $realPath === false ? null : $realPath;
    }

    /**
     * Get the root Composer package name.
     *
     * @return string|null
     */
    protected static function rootPackageName(): ?string
    {
        try {
            $rootPackage = InstalledVersions::getRootPackage();

            return $rootPackage['name'];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get a file path relative to a package root.
     *
     * @param string $directory Package root.
     * @param string $file Absolute file path.
     * @return string
     */
    protected static function relativePath(string $directory, string $file): string
    {
        if ($file === $directory) {
            return basename($file);
        }

        return substr($file, strlen($directory) + 1);
    }

    /**
     * Determine whether a directory contains a file.
     *
     * @param string $directory Directory path.
     * @param string $file File path.
     * @return bool
     */
    protected static function contains(string $directory, string $file): bool
    {
        return $file === $directory || str_starts_with($file, $directory . DIRECTORY_SEPARATOR);
    }
}
