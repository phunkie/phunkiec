<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

/**
 * The class a declaration was written inside, and what it bound.
 *
 * A method reports itself as `Stack::push` and a function as `deepen`, and a
 * type variable in a method's signature stands for whatever the object it was
 * called on is holding. Both questions are about what encloses the
 * declaration, so both are asked of this.
 */
final class Enclosing
{
    /**
     * @param string|null  $name       Name of the class, null outside one and for an
     *                                 anonymous class, which has nothing to be named by
     * @param list<string> $parameters Type parameters it bound, in order
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly array $parameters = [],
    ) {
    }
}
