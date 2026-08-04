<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Core;

/**
 * One .phunkie source, and where it sits in the tree it was found in.
 */
final class SourceFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $relativePath,
    ) {
    }

    /**
     * Identifies the contents, so that a change can be told from a rewrite of
     * the same bytes. Taken from the contents rather than the modification
     * time, because filemtime has one-second resolution: two saves inside the
     * same second look identical by time, and the second would be missed.
     */
    public function fingerprint(): string
    {
        return (string) md5_file($this->path);
    }
}
