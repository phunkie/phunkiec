<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

/**
 * What a function promised in type arguments, kept aside while the brackets
 * that said it are erased.
 */
final class Signature
{
    /**
     * @param list<Parameter> $parameters
     * @param list<string> $returnArguments
     */
    public function __construct(
        public readonly string $function,
        public readonly array $parameters,
        public readonly array $returnArguments,
    ) {
    }

    /**
     * A signature that promised nothing needs no guard, and is not worth
     * carrying: it is what every ordinary function has.
     */
    public function isEmpty(): bool
    {
        return $this->parameters === [] && $this->returnArguments === [];
    }
}
