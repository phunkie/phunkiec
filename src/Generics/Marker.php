<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;

/**
 * What a signature promised, travelling on the declaration that promised it.
 *
 * Erasure takes the type arguments out of the code, and the guards go in much
 * later, on a syntax tree, after the macros have run. Something has to carry
 * what was erased across that gap.
 *
 * It rides on the declaration itself, as an attribute, rather than in a table
 * beside the code. Two things follow. Nothing has to be looked up, so a closure
 * is served as well as a named function, having no name to look up by. And
 * nothing can be looked up wrongly: the arguments are attached to the very
 * declaration they came from, whatever the macros move around in between.
 *
 * The attribute never reaches the output. It is read and removed in the same
 * pass that writes the guards.
 */
final class Marker
{
    public const NAME = '__PhunkieGenerics';

    /**
     * Written as one JSON string rather than as attribute arguments, because
     * this is read back by the compiler and never by a person.
     *
     * @param Signature $signature What the declaration promised
     *
     * @return string The attribute to put in front of the declaration
     */
    public function write(Signature $signature): string
    {
        $promised = [
            'f' => $signature->function,
            'p' => array_map(
                static fn (Parameter $parameter): array => [
                    $parameter->position,
                    $parameter->name,
                    $parameter->arguments,
                    $parameter->variable,
                ],
                $signature->parameters
            ),
            'r' => $signature->returnArguments,
            'o' => $signature->needsOwner(),
        ];

        return sprintf("#[%s('%s')]", self::NAME, json_encode($promised));
    }

    /**
     * Whether anything in this file promised anything at all.
     */
    public function isPresentIn(string $code): bool
    {
        return str_contains($code, self::NAME);
    }

    public function readFrom(Node $node): ?Signature
    {
        if (!property_exists($node, 'attrGroups')) {
            return null;
        }

        foreach ($node->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($attribute->name->toString() !== self::NAME) {
                    continue;
                }

                return $this->decode($attribute);
            }
        }

        return null;
    }

    /**
     * Takes the marker back out, from the printed text rather than from the
     * tree.
     *
     * Removing it as a node would count as changing the declaration, and the
     * printer only reprints what changed: the signature would come back laid
     * out afresh, in its own style rather than the one it was written in. Taken
     * out of the text afterwards, nothing but the guards has moved.
     */
    public function stripFrom(string $code): string
    {
        return (string) preg_replace('/#\[' . self::NAME . "\\('[^']*'\\)\\] ?/", '', $code);
    }

    private function decode(Attribute $attribute): ?Signature
    {
        $argument = $attribute->args[0] ?? null;

        if (!$argument instanceof Arg || !$argument->value instanceof String_) {
            return null;
        }

        $promised = json_decode($argument->value->value, true);

        if (!is_array($promised)) {
            return null;
        }

        $parameters = array_map(
            static fn (array $parameter): Parameter => new Parameter($parameter[0], $parameter[1], $parameter[2], $parameter[3]),
            $promised['p']
        );

        return new Signature($promised['f'], $parameters, $promised['r'], $promised['o']);
    }

    /**
     * The name the guard reports, worded as PHP words it: a method carries the
     * class it belongs to, and a closure has no name to carry.
     */
    public function nameFor(?string $class, ?string $function): string
    {
        if ($function === null) {
            return '{closure}';
        }

        return $class === null ? $function : $class . '::' . $function;
    }
}
