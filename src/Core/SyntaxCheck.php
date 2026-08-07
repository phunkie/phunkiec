<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Core;

use ParseError;
use RuntimeException;

/**
 * Reads the compiled PHP the way PHP will.
 *
 * A macro that matches nothing leaves its input alone, which is the right
 * behaviour and an invisible failure: the compiler has no opinion on syntax it
 * does not recognise, so an unerased type argument or a rule that did not fire
 * came out as "Compiled 1 file(s) successfully" and a file `php -l` rejects.
 *
 * This is `php -l` without the process. TOKEN_PARSE runs PHP's own parser, in
 * the version doing the compiling, so the answer is the one PHP would give, and
 * a watch recompiling on every save does not pay for a fork each time.
 */
final class SyntaxCheck
{
    /**
     * @param string $code    Compiled PHP
     * @param int    $addedTo Lines the compiler put in front of what the reader
     *                        wrote, which come off again before the line is named
     *
     * @throws RuntimeException when the code is not PHP that parses
     */
    public function assertParses(string $code, int $addedTo = 0): void
    {
        if (trim($code) === '') {
            return;
        }

        try {
            token_get_all($code, TOKEN_PARSE);
        } catch (ParseError $e) {
            throw new RuntimeException(sprintf(
                'The compiled PHP does not parse: %s on line %d.',
                $e->getMessage(),
                max(1, $e->getLine() - $addedTo)
            ));
        }
    }
}
