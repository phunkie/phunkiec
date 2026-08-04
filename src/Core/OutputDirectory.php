<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Core;

/**
 * Where compiled PHP goes. A source keeps the place it had in the tree it came
 * from, so that the two trees mirror each other and an editor can jump between
 * them.
 */
final class OutputDirectory
{
    public function __construct(
        private readonly string $path,
    ) {
    }

    public function forSource(string $relativePath): string
    {
        return $this->path . '/' . preg_replace('/\.phunkie$/', '.php', $relativePath);
    }
}
