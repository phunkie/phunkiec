<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Watch;

/**
 * Reports sources as they change, for as long as the process runs.
 */
interface WatcherInterface
{
    /**
     * Watches a directory of sources, handing each batch of changes to the
     * callback as it is noticed. It does not return: a watch ends when the
     * process is stopped.
     *
     * @param callable(list<string>): void $onChange receives relative paths
     */
    public function watch(string $directory, callable $onChange): void;
}
