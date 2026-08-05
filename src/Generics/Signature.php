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
        public readonly bool $owned = false,
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

    /**
     * Whether the guards need the object they were called on, which they do
     * wherever a type variable is mentioned: what it stands for is that
     * object's business.
     */
    public function needsOwner(): bool
    {
        return $this->owned;
    }
}
