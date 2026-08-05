<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Core;

use Symfony\Component\Finder\Finder;

/**
 * A directory of .phunkie sources. It is the one place that decides what counts
 * as a source, so compiling a tree and watching one cannot disagree about it.
 */
final class SourceTree
{
    public function __construct(
        private readonly string $directory,
    ) {
    }

    /**
     * A tree that is not there has no sources. A watch outlives the directory
     * it polls: a branch switch or a rename takes it away for a moment, and
     * that is not a reason to stop watching.
     *
     * @return list<SourceFile>
     */
    public function files(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $finder = new Finder();
        $finder->files()->name('*.phunkie')->in($this->directory);

        $sources = [];
        foreach ($finder as $file) {
            $sources[] = new SourceFile((string) $file->getRealPath(), $file->getRelativePathname());
        }

        return $sources;
    }
}
