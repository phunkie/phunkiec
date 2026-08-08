<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

use Phunkie\Stan\Type\ClassSynthesis;
use Phunkie\Stan\Type\SynthesisParameter;

/**
 * Writes the class a bodyless declaration meant.
 *
 * `final class Some<T>(T $value) extends Option<T>;` states what the class is
 * made of, and this writes the rest: one public readonly property per
 * parameter, and the constructor that fills them. Readonly is the promise the
 * primary constructor makes, and where the written type was a variable, the
 * property is typed mixed, because readonly must be typed and the variable is
 * nothing to PHP.
 */
final class SynthesisWriter
{
    /**
     * The members alone, for a declaration whose braces came back: the class
     * stays as written, and these go just inside the body's opening brace.
     *
     * @param ClassSynthesis $synthesis What the reader declared
     *
     * @return string Properties and constructor, indented for a class body
     */
    public function members(ClassSynthesis $synthesis): string
    {
        return "\n" . $this->properties($synthesis)
            . "\n\n    public function __construct(" . $this->arguments($synthesis) . ")\n    {\n"
            . $this->assignments($synthesis)
            . "\n    }\n";
    }

    /**
     * @param ClassSynthesis $synthesis What the reader declared
     *
     * @return string The class as PHP, ready to stand where the declaration stood
     */
    public function write(ClassSynthesis $synthesis): string
    {
        $head = $synthesis->head;

        if ($synthesis->parent !== null) {
            $head .= ' extends ' . $synthesis->parent;
        }

        if ($synthesis->parameters === []) {
            return $head . "\n{\n}";
        }

        return $head . "\n{" . $this->members($synthesis) . "}";
    }

    private function properties(ClassSynthesis $synthesis): string
    {
        return implode("\n", array_map(
            static fn (SynthesisParameter $parameter): string => sprintf('    public readonly %s $%s;', $parameter->phpType ?? 'mixed', $parameter->name),
            $synthesis->parameters
        ));
    }

    private function arguments(ClassSynthesis $synthesis): string
    {
        return implode(', ', array_map(
            static fn (SynthesisParameter $parameter): string => sprintf('%s $%s', $parameter->phpType ?? 'mixed', $parameter->name),
            $synthesis->parameters
        ));
    }

    private function assignments(ClassSynthesis $synthesis): string
    {
        return implode("\n", array_map(
            static fn (SynthesisParameter $parameter): string => sprintf('        $this->%s = $%s;', $parameter->name, $parameter->name),
            $synthesis->parameters
        ));
    }
}
