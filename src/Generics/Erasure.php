<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Phunkie\Stan\Source\Region;
use Phunkie\Stan\Type\ClassSynthesis;
use Phunkie\Stan\Type\Notation;
use Phunkie\Stan\Type\ReadNotation;
use RuntimeException;

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
        private readonly SynthesisWriter $writer = new SynthesisWriter(),
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

        if ($read->erasures === [] && $read->substitutions === [] && $read->syntheses === []) {
            return $source;
        }

        $removals = array_map(
            static fn (Region $erasure): Edit => new Edit($erasure),
            $read->erasures
        );

        // A substitution is notation the output keeps in another spelling,
        // `typeclass` becoming `interface`. The stand-in already wrote the
        // spelling, at the same offsets, so the compiler adopts its text
        // rather than forming a second opinion about what the word should be.
        $substitutions = array_map(
            static fn (Region $region): Edit => new Edit(
                $region,
                substr($read->php, $region->from, $region->to - $region->from)
            ),
            $read->substitutions
        );

        // A synthesis is the one notation the compiler writes rather than
        // removes: the declaration stated what the class is made of, and the
        // class itself has to come from somewhere.
        $written = array_map(
            fn (ClassSynthesis $synthesis): Edit => new Edit($synthesis->region, $this->writer->write($synthesis)),
            $read->syntheses
        );

        return (new Edits(array_merge($removals, $substitutions, $written, $this->promised($read))))->applyTo($source);
    }

    /**
     * What the declarations in a source promised, and what has to come out of
     * them beyond the notation itself.
     *
     * @throws RuntimeException When no declaration in the source could be read
     *
     * @return list<Edit>
     */
    private function promised(ReadNotation $read): array
    {
        $nodes = $this->parse($read->php);

        // A file whose only notation was bodyless classes stands in as empty
        // statements, so an empty tree is what success looks like: there are
        // no declarations left to promise anything. With no synthesis to
        // account for it, an empty tree means nothing could be read at all.
        if ($nodes === [] && $read->syntheses === []) {
            throw new RuntimeException(
                'The source promised something in its types, and none of its declarations could be read.'
            );
        }

        $visitor = new ErasureVisitor($this->marker, $read);

        (new NodeTraverser($visitor))->traverse($nodes);

        return $visitor->edits();
    }

    /**
     * Reads the declarations out of a source that is not PHP yet.
     *
     * It is not PHP yet on purpose: this runs before the macros, so a
     * comprehension or a match is still written the way phunkie writes it and
     * PHP has no idea what either is. That is why the errors are collected
     * rather than thrown. A statement PHP cannot read says nothing about the
     * declaration it sits in, and the guards are about declarations.
     *
     * The scanner this replaced never had the problem, because tokens do not
     * have to mean anything. Reading a tree bought everything else and cost
     * this, and recovery is what buys it back until the grammar reads
     * statements too.
     *
     * @return array<Node> The declarations that could be read, which is every
     *                     one of them whenever only statements were broken
     */
    private function parse(string $php): array
    {
        return $this->parser->parse($php, new Collecting()) ?? [];
    }
}
