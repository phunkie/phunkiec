<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

/**
 * PHP that parses, and the promises taken out of it to make it parse.
 */
final class ErasedSource
{
    public function __construct(
        public readonly string $code,
        public readonly Signatures $signatures,
    ) {
    }
}
