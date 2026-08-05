<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Watch;

/**
 * What a source tree looked like at one moment, as a fingerprint per source.
 */
final class SourceSnapshot
{
    /**
     * @param array<string, string> $fingerprints relative path => fingerprint
     */
    public function __construct(
        private readonly array $fingerprints,
    ) {
    }

    /**
     * The sources that are new, or whose contents have moved on, since the
     * snapshot given.
     *
     * A source that has gone is not reported: it cannot be recompiled, and the
     * PHP it produced is left where it is. Deleting generated files on the
     * strength of a poll is a worse failure than leaving one behind.
     *
     * @return list<string>
     */
    public function changedSince(self $previous): array
    {
        $changed = [];

        foreach ($this->fingerprints as $relativePath => $fingerprint) {
            if (!$previous->holds($relativePath, $fingerprint)) {
                $changed[] = $relativePath;
            }
        }

        return $changed;
    }

    private function holds(string $relativePath, string $fingerprint): bool
    {
        return ($this->fingerprints[$relativePath] ?? null) === $fingerprint;
    }
}
