<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Core;

use PhpParser\Error;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Phunkie\Stan\Source\OpeningTag as StanOpeningTag;
use Phunkie\Stan\Type\Notation;
use Phunkie\Stan\Type\ReadNotation;
use Phunkie\Stan\Type\TypeSyntaxError;
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

    private Parser $parser;

    public function __construct()
    {
        $this->notation = new Notation();
        $this->openingTag = new StanOpeningTag();
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
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
        $error = $this->mistakeIn($read);

        if ($error === null) {
            return;
        }

        $position = $opened->positionOf($error->offset);

        throw new RuntimeException(sprintf(
            '%s on line %d, column %d.',
            rtrim($error->getMessage(), '.'),
            $position->line,
            $position->column
        ));
    }

    /**
     * Which piece of unread notation was a mistake, rather than PHP the grammar
     * had no business reading.
     *
     * The grammar reports what it could not read as a suspicion and not as a
     * verdict, because a name in front of a bracket looks the same whether it
     * is broken notation or arithmetic. PHP settles it: asked about the same
     * source, it gives up inside the notation when the notation is the problem,
     * and somewhere else, or nowhere at all, when it never was. `[(FOO) => $x]`
     * is refused by this grammar and read happily by PHP, so it is PHP.
     *
     * Asked per suspect. A source arrives here before the macros have run, so
     * PHP is expected to complain about the comprehensions and the matches it
     * cannot read yet, and none of that says anything about a type somewhere
     * else in the file.
     */
    private function mistakeIn(ReadNotation $read): ?TypeSyntaxError
    {
        if (!$read->hasErrors()) {
            return null;
        }

        $handler = new Collecting();
        $this->parser->parse($read->php, $handler);

        foreach ($read->errors as $suspect) {
            foreach ($handler->getErrors() as $complaint) {
                if ($suspect->covers($this->offsetOf($complaint))) {
                    return $suspect;
                }
            }
        }

        return null;
    }

    private function offsetOf(Error $error): int
    {
        $offset = $error->getAttributes()['startFilePos'] ?? -1;

        return is_int($offset) ? $offset : -1;
    }
}
