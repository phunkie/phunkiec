<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

use Phunkie\Stan\Type\CompanionSynthesis;
use Phunkie\Stan\Type\SynthesisParameter;

/**
 * Writes the constructor function a `#[Companion]` asked for.
 *
 * Every function is global, which is what lets `Some(42)` be written from any
 * namespace, and every one is guarded with function_exists so a file loaded
 * twice declares nothing twice. A recipe's body only ever calls the cases'
 * own companions and constants, so it needs their names and nothing else.
 *
 * A parameter keeps its type when PHP would read it the same way everywhere
 * (built-ins, and names already fully qualified); a short class name would
 * resolve against the global namespace instead of the source's, so it widens
 * to mixed and the synthesized constructor, which still holds the written
 * type, enforces it one call deeper.
 */
final class CompanionWriter
{
    private const BUILT_IN = ['int', 'float', 'string', 'bool', 'array', 'object', 'callable', 'iterable', 'mixed'];

    /**
     * Every companion of a file, as one block of global declarations.
     *
     * @param list<CompanionSynthesis> $companions What the attributes declared
     * @param string|null              $namespace  Namespace the classes live in
     */
    public function block(array $companions, ?string $namespace): string
    {
        return implode("\n\n", array_map(fn (CompanionSynthesis $companion): string => $this->one($companion, $namespace), $companions));
    }

    private function one(CompanionSynthesis $companion, ?string $namespace): string
    {
        $fqn = '\\' . ($namespace !== null ? $namespace . '\\' : '') . $companion->class;

        if ($companion->variadic !== null) {
            [$cons, $empty] = $companion->variadic;

            return $this->guarded($companion->class, sprintf('mixed ...$values): %s', $fqn),
                "        \$list = {$empty};\n\n"
                . "        foreach (array_reverse(\$values) as \$value) {\n"
                . "            \$list = {$cons}(\$value, \$list);\n"
                . "        }\n\n"
                . "        return \$list;\n");
        }

        if ($companion->nullable !== null) {
            [$some, $empty] = $companion->nullable;

            return $this->guarded($companion->class, sprintf('mixed $value = null): %s', $fqn),
                "        return null === \$value ? {$empty} : {$some}(\$value);\n");
        }

        if ($companion->singleton) {
            $function = $this->guarded($companion->class, sprintf('): %s', $fqn),
                "        static \$instance;\n\n"
                . "        return \$instance ??= new {$fqn}();\n");

            if ($companion->withArguments) {
                return $function;
            }

            return $function . "\n\n"
                . "if (!defined('{$companion->class}')) {\n"
                . "    define('{$companion->class}', {$companion->class}());\n"
                . '}';
        }

        return $this->guarded($companion->class, sprintf('%s): %s', $this->arguments($companion), $fqn),
            sprintf("        return new %s(%s);\n", $fqn, $this->forwarded($companion)));
    }

    private function guarded(string $name, string $signatureTail, string $body): string
    {
        return "if (!function_exists('{$name}')) {\n"
            . "    function {$name}({$signatureTail}\n"
            . "    {\n"
            . $body
            . "    }\n"
            . '}';
    }

    private function arguments(CompanionSynthesis $companion): string
    {
        return implode(', ', array_map(
            fn (SynthesisParameter $parameter): string => sprintf('%s $%s', $this->carried($parameter->phpType), $parameter->name),
            $companion->parameters
        ));
    }

    private function forwarded(CompanionSynthesis $companion): string
    {
        return implode(', ', array_map(
            static fn (SynthesisParameter $parameter): string => '$' . $parameter->name,
            $companion->parameters
        ));
    }

    private function carried(?string $phpType): string
    {
        if ($phpType === null) {
            return 'mixed';
        }

        if (str_starts_with($phpType, '\\') || in_array(strtolower($phpType), self::BUILT_IN, true)) {
            return $phpType;
        }

        return 'mixed';
    }
}
