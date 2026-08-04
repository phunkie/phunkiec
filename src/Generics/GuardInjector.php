<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Throwable;

/**
 * Writes the guards into the erased PHP.
 *
 * The printing preserves the original formatting, so only the statements that
 * gained a guard are laid out afresh and the rest of the file comes through
 * exactly as it was written. A compiler that reformatted every file it touched
 * would make its own output the subject of every review.
 */
final class GuardInjector
{
    public function inject(string $code, Signatures $signatures): string
    {
        if ($signatures->isEmpty()) {
            return $code;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $original = $parser->parse($code);
        } catch (Throwable) {
            // Erasure left something that will not parse. The compile is
            // already wrong, and saying so where the code is read is better
            // than throwing from here, where all that is known is that a guard
            // could not be placed.
            return $code;
        }

        if ($original === null) {
            return $code;
        }

        $guarded = (new NodeTraverser(new CloningVisitor()))->traverse($original);
        $guarded = (new NodeTraverser(new GuardVisitor($signatures)))->traverse($guarded);

        return (new Standard())->printFormatPreserving($guarded, $original, $parser->getTokens());
    }
}
