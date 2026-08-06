<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Phunkie\Stan\Source\Region;
use Phunkie\Stan\Type\Notation;
use Phunkie\Stan\Type\ReadNotation;

/**
 * Takes the type notation out of a source, and remembers what it said.
 *
 * Two readers used to look at the same text: the grammar, to say whether it
 * was phunkie, and a scanner here, to take it out. They agreed about
 * `ImmList<Int>` and disagreed about everything else, and every disagreement
 * looked the same from the outside, as PHP that would not parse. So a
 * qualified name, an array of a type and a function's shape were all read
 * happily by one and left untouched by the other.
 *
 * There is one reader now. The grammar says where the notation was and this
 * removes exactly that, so the two cannot drift: notation the grammar learns
 * to read is notation this erases, without a line changing here.
 *
 * What is left is the part that is not about the notation but about the
 * declaration carrying it: whose signature promised what, and which parameter
 * of it. That is structure, so it is read from the tree of the source with its
 * notation blanked out, which is the same source with the same offsets.
 */
final class Erasure
{
    private readonly Parser $parser;

    /**
     * @param Notation $notation Reads phunkie's notation and says where it was
     * @param Marker   $marker   Writes what a declaration promised
     */
    public function __construct(
        private readonly Notation $notation = new Notation(),
        private readonly Marker $marker = new Marker(),
    ) {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @param string $source Source, already opened with a PHP tag
     *
     * @return string The same source as PHP, with what it promised marked on
     *                the declarations that promised it
     */
    public function erase(string $source): string
    {
        $read = $this->notation->readFrom($source);

        if ($read->blanks === []) {
            return $source;
        }

        $removals = array_map(
            static fn (Region $blank): Edit => new Edit($blank),
            $read->blanks
        );

        return (new Edits(array_merge($removals, $this->promised($read))))->applyTo($source);
    }

    /**
     * What the declarations in a source promised, and what has to come out of
     * them beyond the notation itself.
     *
     * @return list<Edit>
     */
    private function promised(ReadNotation $read): array
    {
        $nodes = $this->parse($read->php);

        if ($nodes === null) {
            return [];
        }

        $visitor = new ErasureVisitor($this->marker, $read);

        (new NodeTraverser($visitor))->traverse($nodes);

        return $visitor->edits();
    }

    /**
     * @return array<Node>|null The tree, or null where there is none to walk
     */
    private function parse(string $php): ?array
    {
        try {
            return $this->parser->parse($php);
        } catch (Error) {
            // Blanking the notation left something PHP cannot read, so nothing
            // here can say which declaration promised what. The notation still
            // comes out, and saying what is wrong belongs to the check that
            // reads the output rather than to this.
            return null;
        }
    }
}
