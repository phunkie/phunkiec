<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

/**
 * A parameter that named type arguments, and where it sits in the signature.
 *
 * The position counts every parameter, not only the ones that named arguments,
 * because it is the position a caller sees in the error.
 *
 * A parameter either names type arguments, as `ImmList<Int> $xs` does, or is
 * itself a type variable, as `T $item` is. The second stands for whatever the
 * object it was called on is holding, so it is checked differently.
 */
final class Parameter
{
    /**
     * @param list<string> $arguments
     */
    public function __construct(
        public readonly int $position,
        public readonly string $name,
        public readonly array $arguments,
        public readonly ?string $variable = null,
    ) {
    }
}
