<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Core;

use Phunkie\Stan\Source\OpeningTag as StanOpeningTag;
use Phunkie\Stan\Type\Notation;
use RuntimeException;

/**
 * Reads a source with phunkie's grammar before anything tries to compile it.
 *
 * This is the front end the compiler never had. Without it a `.syn` rule that
 * does not match leaves its input alone, which is correct, so the answer to
 * notation nobody has taught it yet is to pass it through: a gap in the
 * compiler's coverage and ordinary PHP going by look exactly the same, and what
 * comes out is PHP that will not parse.
 *
 * The grammar is phunkistan's rather than a second one written here. Two
 * definitions of the same notation drift, and the one thing worse than a
 * compiler with no grammar is a compiler and a checker that disagree about what
 * the language is.
 *
 * The source is handed over exactly as the reader wrote it, tag and all, so the
 * positions that come back are already positions in their file.
 */
final class Grammar
{
    private Notation $notation;

    private StanOpeningTag $openingTag;

    public function __construct()
    {
        $this->notation = new Notation();
        $this->openingTag = new StanOpeningTag();
    }

    /**
     * Reads a source, and says so where it cannot.
     *
     * @param string $source Source exactly as the reader wrote it
     *
     * @throws RuntimeException When the notation cannot be read, naming the place
     */
    public function assertReads(string $source): void
    {
        $opened = $this->openingTag->open($source);
        $read = $this->notation->readFrom($opened->text());

        if (!$read->hasErrors()) {
            return;
        }

        $error = $read->errors[0];
        $position = $opened->positionOf($error->offset);

        throw new RuntimeException(sprintf(
            '%s on line %d, column %d.',
            rtrim($error->getMessage(), '.'),
            $position->line,
            $position->column
        ));
    }
}
