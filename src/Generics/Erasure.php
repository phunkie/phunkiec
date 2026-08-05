<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

use PhpToken;

/**
 * Takes the type arguments out of a signature, and remembers what they said.
 *
 * This runs on tokens rather than on a syntax tree, and it has to: `ImmList<Int>
 * $numbers` is not PHP, so there is no tree to run on until the brackets are
 * gone. Erasing is therefore the first thing that happens to a source and the
 * only chance anything has to read them.
 *
 * It is safe to erase `<`, whatever else is in the file, because PHP 8 made
 * comparison non-associative: `$a < $b > $c` is a parse error rather than an
 * expression, so the shape being matched here can never be a comparison that
 * someone meant.
 *
 * Closures are not covered: there is no name to address a guard to, so their
 * signatures are handed back exactly as written rather than half erased.
 */
final class Erasure
{
    private Marker $marker;

    public function __construct()
    {
        $this->marker = new Marker();
    }

    public function erase(string $source): string
    {
        if (!str_contains($source, '<')) {
            return $source;
        }

        $tokens = PhpToken::tokenize($source);
        $count = count($tokens);
        $emitted = [];
        $classes = [];
        $depth = 0;
        $position = 0;

        while ($position < $count) {
            $token = $tokens[$position];

            if ($token->text === '{') {
                $depth++;
            }

            if ($token->text === '}') {
                $depth--;

                while ($classes !== [] && end($classes)['depth'] >= $depth) {
                    array_pop($classes);
                }
            }

            if ($this->opensAClass($tokens, $position)) {
                $name = $this->nameAfter($tokens, $position);

                if ($name !== null) {
                    $classes[] = ['name' => $name, 'depth' => $depth];
                }
            }

            if (!$token->is(T_FUNCTION)) {
                $emitted[] = $token;
                $position++;

                continue;
            }

            $enclosing = $classes === [] ? null : end($classes);

            [$signatureTokens, $signature, $position] = $this->eraseSignature($tokens, $position, $enclosing);

            if ($signature !== null && !$signature->isEmpty()) {
                $emitted = $this->mark($emitted, $signature);
            }

            $emitted = array_merge($emitted, $signatureTokens);
        }

        return $this->render($emitted);
    }

    /**
     * Puts the marker in front of the declaration.
     *
     * An attribute goes before the modifiers, not after, so `public function`
     * has to be stepped back over to reach the place PHP will accept it.
     *
     * @param list<PhpToken> $emitted
     *
     * @return list<PhpToken>
     */
    private function mark(array $emitted, Signature $signature): array
    {
        return $this->insertMarker($emitted, $this->marker->write(
            $signature->function,
            $signature->parameters,
            $signature->returnArguments
        ));
    }

    /**
     * @param list<PhpToken> $emitted
     *
     * @return list<PhpToken>
     */
    private function insertMarker(array $emitted, string $text): array
    {
        $at = count($emitted);

        while (($before = $this->modifierBefore($emitted, $at)) !== null) {
            $at = $before;
        }

        array_splice($emitted, $at, 0, [
            new PhpToken(T_ATTRIBUTE, $text),
            new PhpToken(T_WHITESPACE, ' '),
        ]);

        return $emitted;
    }

    /**
     * Where a modifier sitting just before this point begins, if one does.
     *
     * Only modifiers are stepped over. Anything else means the declaration
     * starts here: a closure is preceded by whatever it is being assigned to,
     * and moving in front of that would take the marker with it.
     *
     * @param list<PhpToken> $emitted
     */
    private function modifierBefore(array $emitted, int $at): ?int
    {
        while ($at > 0 && $emitted[$at - 1]->is(T_WHITESPACE) && !str_contains($emitted[$at - 1]->text, "\n")) {
            $at--;
        }

        if ($at === 0 || !$this->isModifier($emitted[$at - 1])) {
            return null;
        }

        return $at - 1;
    }

    private function isModifier(PhpToken $token): bool
    {
        return $token->is(T_PUBLIC)
            || $token->is(T_PROTECTED)
            || $token->is(T_PRIVATE)
            || $token->is(T_STATIC)
            || $token->is(T_FINAL)
            || $token->is(T_ABSTRACT)
            || $token->is(T_READONLY);
    }

    /**
     * Whether a class body starts here, as opposed to `Foo::class` being read.
     *
     * @param list<PhpToken> $tokens
     */
    private function opensAClass(array $tokens, int $position): bool
    {
        if (!$tokens[$position]->is(T_CLASS) && !$tokens[$position]->is(T_TRAIT) && !$tokens[$position]->is(T_INTERFACE) && !$tokens[$position]->is(T_ENUM)) {
            return false;
        }

        $previous = $position - 1;

        while ($previous >= 0 && $tokens[$previous]->is(T_WHITESPACE)) {
            $previous--;
        }

        return $previous < 0 || !$tokens[$previous]->is(T_DOUBLE_COLON);
    }

    /**
     * The name a declaration was given, or null where it has none: an anonymous
     * class has nothing a guard could be addressed to.
     *
     * @param list<PhpToken> $tokens
     */
    private function nameAfter(array $tokens, int $position): ?string
    {
        $count = count($tokens);
        $position++;

        while ($position < $count && $tokens[$position]->is(T_WHITESPACE)) {
            $position++;
        }

        return $position < $count && $tokens[$position]->is(T_STRING) ? $tokens[$position]->text : null;
    }

    /**
     * @param list<PhpToken> $tokens
     *
     * @return array{list<PhpToken>, Signature|null, int}
     */
    private function eraseSignature(array $tokens, int $start, ?array $enclosing): array
    {
        $count = count($tokens);
        $emitted = [$tokens[$start]];
        $position = $this->skip($tokens, $start + 1, $emitted, ['&']);

        // A closure has no name. It is guarded all the same, because what it
        // promised travels on the declaration rather than under a name.
        $function = null;

        if ($position < $count && $tokens[$position]->is(T_STRING)) {
            $function = $tokens[$position]->text;
            $emitted[] = $tokens[$position];
            $position = $this->skip($tokens, $position + 1, $emitted, []);
        }

        if ($position >= $count || $tokens[$position]->text !== '(') {
            return [$emitted, null, $position];
        }

        $close = $this->matching($tokens, $position);

        if ($close === -1) {
            return [$emitted, null, $position];
        }

        [$parameterTokens, $parameters] = $this->eraseParameters(
            array_slice($tokens, $position + 1, $close - $position - 1)
        );

        $emitted[] = $tokens[$position];
        $emitted = array_merge($emitted, $parameterTokens, [$tokens[$close]]);
        $position = $close + 1;

        [$returnTokens, $returnArguments, $position] = $this->eraseReturnType($tokens, $position);
        $emitted = array_merge($emitted, $returnTokens);

        $signature = new Signature(
            $this->marker->nameFor($enclosing['name'] ?? null, $function),
            $parameters,
            $returnArguments
        );

        return [$emitted, $signature, $position];
    }

    /**
     * @param list<PhpToken> $tokens
     *
     * @return array{list<PhpToken>, list<Parameter>}
     */
    private function eraseParameters(array $tokens): array
    {
        $emitted = [];
        $parameters = [];
        $position = 0;

        foreach ($this->splitParameters($tokens) as $chunk) {
            if ($position > 0) {
                $emitted[] = new PhpToken(ord(','), ',');
            }

            [$chunk, $arguments] = $this->eraseArguments($chunk);
            $emitted = array_merge($emitted, $chunk);
            $name = $this->variableIn($chunk);

            if ($name === null) {
                continue;
            }

            $position++;

            if ($arguments !== []) {
                $parameters[] = new Parameter($position, $name, $arguments);
            }
        }

        return [$emitted, $parameters];
    }

    /**
     * @param list<PhpToken> $tokens
     *
     * @return array{list<PhpToken>, list<string>, int}
     */
    private function eraseReturnType(array $tokens, int $start): array
    {
        $count = count($tokens);
        $position = $start;

        while ($position < $count && $tokens[$position]->is(T_WHITESPACE)) {
            $position++;
        }

        if ($position >= $count || $tokens[$position]->text !== ':') {
            return [[], [], $start];
        }

        $end = $this->endOfReturnType($tokens, $position);
        [$region, $arguments] = $this->eraseArguments(array_slice($tokens, $start, $end - $start));

        return [$region, $arguments, $end];
    }

    /**
     * Removes one `<...>` group and reads what it said.
     *
     * The group is only taken where a name sits in front of it, which is what a
     * type argument looks like and a comparison does not.
     *
     * @param list<PhpToken> $tokens
     *
     * @return array{list<PhpToken>, list<string>}
     */
    private function eraseArguments(array $tokens): array
    {
        $open = $this->openingBracket($tokens);

        if ($open === -1) {
            return [$tokens, []];
        }

        $tokens = $this->splitShifts($tokens, $open);
        $depth = 0;

        for ($close = $open; $close < count($tokens); $close++) {
            $text = $tokens[$close]->text;

            if ($text === '<') {
                $depth++;
            }

            if ($text === '>') {
                $depth--;

                if ($depth === 0) {
                    break;
                }
            }
        }

        if ($depth !== 0) {
            return [$tokens, []];
        }

        $arguments = array_map(
            fn (array $argument): string => trim($this->render($argument)),
            $this->splitTypeArguments(array_slice($tokens, $open + 1, $close - $open - 1))
        );

        return [array_merge(array_slice($tokens, 0, $open), array_slice($tokens, $close + 1)), $arguments];
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function openingBracket(array $tokens): int
    {
        foreach ($tokens as $position => $token) {
            if ($token->text !== '<') {
                continue;
            }

            $previous = $position - 1;

            while ($previous >= 0 && $tokens[$previous]->is(T_WHITESPACE)) {
                $previous--;
            }

            if ($previous >= 0 && $tokens[$previous]->is(T_STRING)) {
                return $position;
            }
        }

        return -1;
    }

    /**
     * Puts back the brackets PHP's lexer ran together.
     *
     * A nested group closes on `>>`, which lexes as one shift token, and on
     * `>>>` as a shift and a bracket. Inside a type argument there is no shift
     * operator for it to be confused with, so within the group the token is
     * split into the two brackets it stands for. The scan stops where the group
     * does, leaving any real shift further along the line alone: a parameter
     * may well be written `$bits = 8 >> 2`.
     *
     * @param list<PhpToken> $tokens
     *
     * @return list<PhpToken>
     */
    private function splitShifts(array $tokens, int $open): array
    {
        $depth = 0;
        $position = $open;

        while ($position < count($tokens)) {
            $token = $tokens[$position];

            if ($token->is(T_SR) && $depth > 0) {
                array_splice($tokens, $position, 1, [
                    new PhpToken(ord('>'), '>'),
                    new PhpToken(ord('>'), '>'),
                ]);

                continue;
            }

            if ($token->text === '<') {
                $depth++;
            }

            if ($token->text === '>') {
                $depth--;

                if ($depth === 0) {
                    return $tokens;
                }
            }

            $position++;
        }

        return $tokens;
    }

    /**
     * Splits type arguments, which nest in angle brackets rather than in the
     * round ones a parameter list nests in: the comma in
     * `ImmList<ImmMap<String, Int>>` belongs to the map, not to the list.
     *
     * @param list<PhpToken> $tokens
     *
     * @return list<list<PhpToken>>
     */
    private function splitTypeArguments(array $tokens): array
    {
        $chunks = [];
        $current = [];
        $depth = 0;

        foreach ($tokens as $token) {
            if ($token->text === '<') {
                $depth++;
            }

            if ($token->text === '>') {
                $depth--;
            }

            if ($token->text === ',' && $depth === 0) {
                $chunks[] = $current;
                $current = [];

                continue;
            }

            $current[] = $token;
        }

        if ($current !== []) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * Splits a parameter list into its parameters.
     *
     * Angle brackets are counted as well as round ones, because the comma in
     * `ImmList<ImmMap<String, Int>> $rows` belongs to the map rather than to
     * the list. They stop being counted once a `=` has been seen: a type
     * declaration comes before its default value, so a `<` beyond that point is
     * a comparison in a constant expression and not a bracket looking for its
     * pair. That also leaves a genuine shift alone, as in `$bits = 8 >> 2`.
     *
     * @param list<PhpToken> $tokens
     *
     * @return list<list<PhpToken>>
     */
    private function splitParameters(array $tokens): array
    {
        $chunks = [];
        $current = [];
        $depth = 0;
        $angle = 0;
        $default = false;

        foreach ($tokens as $token) {
            if (in_array($token->text, ['(', '['], true)) {
                $depth++;
            }

            if (in_array($token->text, [')', ']'], true)) {
                $depth--;
            }

            if ($token->text === '=') {
                $default = true;
            }

            if (!$default) {
                $angle += match (true) {
                    $token->text === '<' => 1,
                    $token->text === '>' => -1,
                    $token->is(T_SR) => -2,
                    default => 0,
                };
            }

            if ($token->text === ',' && $depth === 0 && $angle <= 0) {
                $chunks[] = $current;
                $current = [];
                $angle = 0;
                $default = false;

                continue;
            }

            $current[] = $token;
        }

        if ($current !== []) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function variableIn(array $tokens): ?string
    {
        foreach ($tokens as $token) {
            if ($token->is(T_VARIABLE)) {
                return ltrim($token->text, '$');
            }
        }

        return null;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function matching(array $tokens, int $open): int
    {
        $depth = 0;

        for ($position = $open; $position < count($tokens); $position++) {
            if ($tokens[$position]->text === '(') {
                $depth++;
            }

            if ($tokens[$position]->text === ')') {
                $depth--;

                if ($depth === 0) {
                    return $position;
                }
            }
        }

        return -1;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function endOfReturnType(array $tokens, int $colon): int
    {
        $count = count($tokens);
        $depth = 0;

        for ($position = $colon; $position < $count; $position++) {
            $text = $tokens[$position]->text;

            if ($text === '(') {
                $depth++;
            }

            if ($text === ')') {
                $depth--;
            }

            if ($depth === 0 && ($text === '{' || $text === ';')) {
                return $position;
            }
        }

        return $count;
    }

    /**
     * @param list<PhpToken> $tokens
     * @param list<PhpToken> $emitted
     * @param list<string> $also text of tokens to pass over as well as whitespace
     */
    private function skip(array $tokens, int $position, array &$emitted, array $also): int
    {
        $count = count($tokens);

        while ($position < $count) {
            $token = $tokens[$position];

            if (!$token->is(T_WHITESPACE) && !in_array($token->text, $also, true)) {
                return $position;
            }

            $emitted[] = $token;
            $position++;
        }

        return $position;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function render(array $tokens): string
    {
        return implode('', array_map(static fn (PhpToken $token): string => $token->text, $tokens));
    }
}
