<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeVisitorAbstract;
use Phunkie\Stan\Source\Region;
use Phunkie\Stan\Type\DeclarationHeader;
use Phunkie\Stan\Type\ReadNotation;
use Phunkie\Stan\Type\Type;
use Phunkie\Stan\Type\TypeApplication;
use Phunkie\Stan\Type\TypeParameterDeclaration;

/**
 * Reads what each declaration in a source promised, and says what has to
 * change about it.
 *
 * The notation itself is taken out by the grammar that read it, which knows
 * exactly where it was. What is left is everything that needs to know which
 * declaration the notation belonged to: whose signature promised what, which
 * parameter of it, and where a marker may go so that PHP will still parse the
 * declaration it is attached to. All of those are structure, and this walks
 * the structure rather than guessing at it from tokens.
 *
 * It walks the source with the notation blanked out, which is the same source
 * with the same length, so every offset a node reports is an offset in the
 * file the reader wrote.
 */
final class ErasureVisitor extends NodeVisitorAbstract
{
    /** @var list<Edit> */
    private array $edits = [];

    /** @var list<Enclosing> */
    private array $classes = [];

    /**
     * @param Marker       $marker Writes what a declaration promised
     * @param ReadNotation $read   What the grammar found in this source
     */
    public function __construct(
        private readonly Marker $marker,
        private readonly ReadNotation $read,
    ) {
    }

    /**
     * @return list<Edit> Every change the declarations in this source need
     */
    public function edits(): array
    {
        return $this->edits;
    }

    /**
     * @param Node $node Node being entered
     *
     * @return null Nothing is replaced: the tree is read, and the source is
     *              what gets changed
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof ClassLike) {
            $this->enterClass($node);

            return null;
        }

        if ($node instanceof ClassMethod || $node instanceof Function_ || $node instanceof Closure) {
            $this->enterFunction($node);
        }

        return null;
    }

    /**
     * @param Node $node Node being left
     *
     * @return null
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof ClassLike) {
            array_pop($this->classes);
        }

        return null;
    }

    /**
     * A class says it is generic by naming its parameters, and that is all it
     * has to say: how many it takes. What they are is read from the value,
     * which is why only the count travels.
     */
    private function enterClass(ClassLike $node): void
    {
        $header = $node->name === null ? null : $this->headerAt($node->name->getStartFilePos());
        $parameters = $header === null ? [] : $this->namesOf($header);

        $this->classes[] = new Enclosing($node->name?->toString(), $parameters);

        if ($parameters === []) {
            return;
        }

        $this->insertBefore($node, $this->marker->writeType($parameters));
    }

    /**
     * A declaration is read against two sets of type parameters, and they are
     * not interchangeable.
     *
     * What the class bound is what a guard can resolve: `assertTypeVariable`
     * asks the object it was called on what it is holding. What the
     * declaration bound itself, as `map<A, B>` does, is held by nobody, so
     * naming it in a promise would write a guard that goes looking for a class
     * called A. Those come out of the source all the same, because `A $a` is
     * not PHP, and they promise nothing until there is somewhere for a
     * method's own arguments to live.
     */
    private function enterFunction(ClassMethod|Function_|Closure $node): void
    {
        $enclosing = $this->enclosing();
        $held = $enclosing->parameters;
        $bound = $this->namesBoundBy($node);
        $types = $this->typesIn($node);

        $parameters = [];
        $position = 0;
        $after = $node->getStartFilePos();

        foreach ($node->getParams() as $param) {
            $position++;
            $name = $this->nameOf($param);
            $ends = $param->getEndFilePos() + 1;
            $arguments = $this->checkable($this->argumentsOf($this->typeBetween($types, $after, $ends)), $bound);
            $stands = $this->typeVariableOf($param, $held, $bound);
            $after = $ends;

            if ($name === null || ($arguments === [] && $stands === null)) {
                continue;
            }

            $parameters[] = new Parameter($position, $name, $arguments, $stands);
        }

        $returnArguments = $this->returnArgumentsOf($node, $types, $after, $held, $bound);

        $signature = new Signature(
            $this->marker->nameFor($enclosing->name, $node instanceof Closure ? null : $node->name->toString()),
            $parameters,
            $returnArguments,
            $this->mentions($parameters, $returnArguments, $held)
        );

        if ($signature->isEmpty()) {
            return;
        }

        $this->insertBefore($node, $this->marker->write($signature));
    }

    /**
     * What a return type promised.
     *
     * A return type that is itself a type variable has no class behind it, so
     * the declaration goes entirely and only the promise is kept.
     *
     * @param list<Type>   $types
     * @param list<string> $held  Type parameters the enclosing class bound
     * @param list<string> $bound Type parameters the declaration bound itself
     *
     * @return list<string>
     */
    private function returnArgumentsOf(ClassMethod|Function_|Closure $node, array $types, int $after, array $held, array $bound): array
    {
        $returnType = $node->getReturnType();

        if ($returnType instanceof Name && in_array($returnType->toString(), array_merge($held, $bound), true)) {
            $this->edits[] = new Edit(new Region(
                $this->colonBefore($returnType->getStartFilePos()),
                $returnType->getEndFilePos() + 1
            ));

            return $this->checkable([$returnType->toString()], $bound);
        }

        return $this->checkable($this->argumentsOf($this->typeBetween($types, $after, $this->bodyStartOf($node))), $bound);
    }

    /**
     * What a promise is worth making.
     *
     * A promise naming a type parameter the declaration bound itself cannot be
     * checked by anything: there is no object holding it and no class of that
     * name. Making it anyway would turn a source that used to refuse to
     * compile into one that compiles and then fails looking for a class called
     * A, which is the worse of the two.
     *
     * @param list<string> $arguments
     * @param list<string> $bound
     *
     * @return list<string>
     */
    private function checkable(array $arguments, array $bound): array
    {
        return array_intersect($arguments, $bound) === [] ? $arguments : [];
    }

    /**
     * Removes a parameter's type declaration where it is a type variable.
     *
     * `T $item` has no class behind it, so PHP has nothing to enforce and the
     * declaration goes. What T stands for is settled when the code runs, from
     * the object the method was called on.
     *
     * @param list<string> $held  Type parameters the enclosing class bound
     * @param list<string> $bound Type parameters the declaration bound itself
     *
     * @return string|null What it stands for, where that is something a guard
     *                     could go and ask about
     */
    private function typeVariableOf(Param $param, array $held, array $bound): ?string
    {
        if (!$param->type instanceof Name || !in_array($param->type->toString(), array_merge($held, $bound), true)) {
            return null;
        }

        $this->edits[] = new Edit(new Region($param->type->getStartFilePos(), $param->type->getEndFilePos() + 1));

        return in_array($param->type->toString(), $held, true) ? $param->type->toString() : null;
    }

    /**
     * Where a return type's colon is, counting back from the type itself.
     *
     * The colon goes with the type it introduces, or `function all() : {` is
     * left behind, which is not PHP. It is found in the source rather than in
     * the tree because PHP keeps no node for it.
     */
    private function colonBefore(int $at): int
    {
        $from = $at;

        while ($at > 0 && $this->read->php[$at - 1] !== ':') {
            $at--;
        }

        return $at === 0 ? $from : $at - 1;
    }

    /**
     * The types written in a declaration's head.
     *
     * The head is everything up to the body, so a type argument written inside
     * the body belongs to nothing here and is left to the grammar to take out.
     *
     * @return list<Type>
     */
    private function typesIn(ClassMethod|Function_|Closure $node): array
    {
        $from = $node->getStartFilePos();
        $to = $this->bodyStartOf($node);

        return array_values(array_filter(
            $this->read->types,
            static fn (Type $type): bool => $type->region()->from >= $from && $type->region()->to <= $to
        ));
    }

    /**
     * The type written between two points, if one was.
     *
     * A type declaration comes in front of the variable it declares, so a
     * parameter's type is the one that ends before the parameter does and
     * begins after the parameter in front of it. That is the language rather
     * than a guess about it, and it holds where containment does not: a
     * callable type leaves nothing behind for PHP to keep a node for, so the
     * parameter does not reach back far enough to contain what declared it.
     *
     * @param list<Type> $types
     */
    private function typeBetween(array $types, int $from, int $to): ?Type
    {
        foreach ($types as $type) {
            if ($type->region()->from >= $from && $type->region()->to <= $to) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @return list<string> The arguments a type supplied, none where it is not
     *                      an application of one
     */
    private function argumentsOf(?Type $type): array
    {
        if (!$type instanceof TypeApplication) {
            return [];
        }

        return array_map('strval', $type->arguments);
    }

    /**
     * The type parameters a declaration bound itself, as `map<A, B>` does.
     *
     * @return list<string>
     */
    private function namesBoundBy(ClassMethod|Function_|Closure $node): array
    {
        if ($node instanceof Closure) {
            return [];
        }

        $header = $this->headerAt($node->name->getStartFilePos());

        return $header === null ? [] : $this->namesOf($header);
    }

    /**
     * The head declared at an offset, which is the name's own offset: the
     * grammar reads a head from the name it declares, so the two agree without
     * anything having to be matched.
     */
    private function headerAt(int $at): ?DeclarationHeader
    {
        foreach ($this->read->headers as $header) {
            if ($header->region->from === $at) {
                return $header;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function namesOf(DeclarationHeader $header): array
    {
        return array_map(
            static fn (TypeParameterDeclaration $parameter): string => $parameter->name,
            $header->parameters
        );
    }

    /**
     * Where a declaration stops and its body begins.
     *
     * A declaration with no body at all, as an interface method has, is its own
     * end.
     */
    private function bodyStartOf(ClassMethod|Function_|Closure $node): int
    {
        $statements = $node->getStmts();

        if ($statements === null || $statements === []) {
            return $node->getEndFilePos() + 1;
        }

        return $statements[0]->getStartFilePos();
    }

    /**
     * Puts a marker in front of a declaration.
     *
     * In front of the whole declaration, which is where its modifiers and its
     * own attributes already are, so nothing has to be stepped back over to
     * find the place PHP will accept one.
     */
    private function insertBefore(Node $node, string $text): void
    {
        $at = $node->getStartFilePos();

        $this->edits[] = new Edit(new Region($at, $at), $text . ' ');
    }

    private function nameOf(Param $param): ?string
    {
        if (!$param->var instanceof Variable || !is_string($param->var->name)) {
            return null;
        }

        return $param->var->name;
    }

    private function enclosing(): Enclosing
    {
        return $this->classes === [] ? new Enclosing() : $this->classes[count($this->classes) - 1];
    }

    /**
     * Whether anything in the signature named one of the parameters its class
     * bound, which is what decides whether the guards need the object they
     * were called on.
     *
     * @param list<Parameter> $parameters
     * @param list<string>    $returnArguments
     * @param list<string>    $held
     */
    private function mentions(array $parameters, array $returnArguments, array $held): bool
    {
        foreach ($parameters as $parameter) {
            if ($parameter->variable !== null || array_intersect($parameter->arguments, $held) !== []) {
                return true;
            }
        }

        return array_intersect($returnArguments, $held) !== [];
    }
}
