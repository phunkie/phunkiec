<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

/**
 * What every function in a file promised, by name.
 *
 * This is the record erasure leaves behind. The brackets are gone from the code
 * by the time anything can parse it, so what they said has to be carried here
 * instead, from the pass that could read them to the pass that can place a
 * guard.
 */
final class Signatures
{
    /**
     * @param array<string, Signature> $signatures
     */
    public function __construct(
        private readonly array $signatures,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->signatures === [];
    }

    public function forFunction(string $name): ?Signature
    {
        return $this->signatures[$name] ?? null;
    }
}
