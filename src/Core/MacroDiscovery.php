<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Core;

use Composer\InstalledVersions;

/**
 * Finds `.syn` macro directories shipped by installed composer packages.
 *
 * A package opts in by declaring, in its own composer.json:
 *
 *     "extra": { "phunkiec": { "macros": "macros/" } }
 *
 * where the path is a directory relative to the package root. This is how a
 * library such as phunkie/effect ships pattern-matching rules for its own types
 * without phunkiec having to know about them.
 *
 * The manifest-reading is kept separate from the InstalledVersions lookup so it
 * can be exercised against fixture package roots.
 */
final class MacroDiscovery
{
    /**
     * @param list<string> $packageRoots directories to inspect, each expected to
     *                                    hold a composer.json
     */
    public function __construct(private array $packageRoots)
    {
    }

    /**
     * Builds a discovery over every composer package installed alongside the
     * project being compiled. Returns an empty discovery when composer's runtime
     * metadata is unavailable.
     */
    public static function fromInstalledPackages(): self
    {
        if (!class_exists(InstalledVersions::class)) {
            return new self([]);
        }

        $roots = [];

        foreach (InstalledVersions::getInstalledPackages() as $package) {
            $path = InstalledVersions::getInstallPath($package);

            if ($path !== null) {
                $roots[] = $path;
            }
        }

        return new self($roots);
    }

    /**
     * @return list<string> absolute paths to the macro directories that exist,
     *                      in package order
     */
    public function discover(): array
    {
        $directories = [];

        foreach ($this->packageRoots as $root) {
            $macros = $this->macrosPathOf($root . '/composer.json');

            if ($macros === null) {
                continue;
            }

            $directory = $root . '/' . $macros;

            if (is_dir($directory)) {
                $directories[] = $directory;
            }
        }

        return $directories;
    }

    /**
     * The value of `extra.phunkiec.macros` from a package manifest, or null when
     * the manifest is absent, unreadable, or does not opt in.
     */
    private function macrosPathOf(string $manifest): ?string
    {
        if (!is_file($manifest)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($manifest), true);

        if (!is_array($decoded)) {
            return null;
        }

        $macros = $decoded['extra']['phunkiec']['macros'] ?? null;

        return is_string($macros) ? trim($macros, '/') : null;
    }
}
