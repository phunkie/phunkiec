<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

use Phunkie\Stan\Type\BlockMethod;

/**
 * Writes the method a block property meant.
 *
 * The arms are emitted still in phunkie, as a match on $this, because
 * rewriting a match is the macro's job and it runs after this. The block's
 * own parameters, where it declared any, become the method's, which is what
 * makes `$option->getOrElse(0)` read the same whether getOrElse was written
 * as a body or as a value.
 */
final class BlockMethodWriter
{
    /**
     * @param BlockMethod $method What the reader declared
     *
     * @return string The method as phunkie, one macro away from PHP
     */
    public function write(BlockMethod $method): string
    {
        $parameters = implode(', ', array_map(
            static fn (string $name): string => '$' . $name,
            $method->parameters
        ));

        return sprintf(
            "public function %s(%s)\n    {\n        return \$this match {\n            %s\n        };\n    }",
            $method->name,
            $parameters,
            $method->arms
        );
    }
}
