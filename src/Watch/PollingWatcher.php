<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Watch;

use Phunkie\Compiler\Core\SourceTree;

/**
 * Notices changes by looking. There is no portable way to be told about them:
 * inotify, kqueue and ReadDirectoryChangesW are three different extensions,
 * none of them shipped with PHP, so polling is what works everywhere.
 */
final class PollingWatcher implements WatcherInterface
{
    private const INTERVAL = 250_000;

    public function __construct(
        private readonly int $intervalMicroseconds = self::INTERVAL,
    ) {
    }

    public function watch(string $directory, callable $onChange): void
    {
        $tree = new SourceTree($directory);
        $previous = $this->snapshot($tree);

        while (true) {
            usleep($this->intervalMicroseconds);

            $current = $this->snapshot($tree);
            $changed = $current->changedSince($previous);
            $previous = $current;

            if ($changed !== []) {
                $onChange($changed);
            }
        }
    }

    private function snapshot(SourceTree $tree): SourceSnapshot
    {
        $fingerprints = [];

        foreach ($tree->files() as $source) {
            $fingerprints[$source->relativePath] = $source->fingerprint();
        }

        return new SourceSnapshot($fingerprints);
    }
}
