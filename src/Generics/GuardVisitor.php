<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Identifier;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Const_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeVisitorAbstract;

/**
 * Puts a guard where a type argument used to be.
 *
 * Guards need to know which declaration a `return` belongs to, which is the
 * whole reason this is a tree and not another pass over tokens: a rule matching
 * `return` by shape would also wrap the one inside a nested closure. Here the
 * enclosing declaration is on a stack, and one that promised nothing puts
 * nothing on it, so a `return` inside it is left alone without a rule saying so.
 */
final class GuardVisitor extends NodeVisitorAbstract
{
    private const KIND = 'Phunkie\\Types\\Kind';

    /**
     * @var list<Signature|null>
     */
    private array $enclosing = [];

    public function __construct(
        private readonly Marker $marker,
    ) {
    }

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof FunctionLike) {
            $this->enclosing[] = $this->marker->readFrom($node);
        }

        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof Class_) {
            return $this->makeGeneric($node);
        }

        if ($node instanceof Return_) {
            return $this->guardReturn($node);
        }

        if (!$node instanceof FunctionLike) {
            return null;
        }

        $signature = array_pop($this->enclosing);

        if ($signature === null) {
            return null;
        }

        // An arrow function is a single expression, with nowhere to put a
        // statement. Its expression is what it returns, so it is wrapped in
        // place, and its parameters are left to the declaration it was passed
        // to. A declaration without a body, on an interface or an abstract
        // method, has nothing to guard: whatever implements it does.
        if ($node instanceof ArrowFunction) {
            $node->expr = $this->guardValue($node->expr, $signature);

            return $node;
        }

        if ($node->getStmts() === null) {
            return $node;
        }

        $node->stmts = array_merge($this->guardParameters($signature), $node->getStmts());

        return $node;
    }

    /**
     * Makes a class that declared type parameters into a Kind.
     *
     * A generic type has to be able to say what it is holding, because that is
     * the only thing the guard ever asks. Saying it is the same two answers for
     * every such class, so they are written here rather than by hand: how many
     * parameters it takes, which it declared, and what they are, which is read
     * from the value.
     *
     * A class that already answers for itself is left as it is.
     */
    private function makeGeneric(Class_ $node): ?Node
    {
        $parameters = $this->marker->readTypeFrom($node);

        if ($parameters === null) {
            return null;
        }

        // The names are kept as well as the count, because a method that says
        // `T $item` has to be able to find out which of them T is.
        $node->stmts[] = $this->parametersConstant($parameters);

        if (!$this->declares($node, 'getTypeArity')) {
            $node->stmts[] = $this->arityMethod(count($parameters));
        }

        if (!$this->declares($node, 'getTypeVariables')) {
            $node->stmts[] = $this->variablesMethod();
        }

        if (!$this->implementsKind($node)) {
            $node->implements[] = new Name\FullyQualified(self::KIND);
        }

        return $node;
    }

    private function declares(Class_ $node, string $method): bool
    {
        return $node->getMethod($method) !== null;
    }

    private function implementsKind(Class_ $node): bool
    {
        foreach ($node->implements as $interface) {
            if ($interface->toString() === self::KIND) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $parameters
     */
    private function parametersConstant(array $parameters): ClassConst
    {
        $names = new Array_(
            array_map(static fn (string $name): ArrayItem => new ArrayItem(new String_($name)), $parameters),
            ['kind' => Array_::KIND_SHORT]
        );

        $constant = new ClassConst([new Const_('typeParameters', $names)]);
        $constant->flags = Modifiers::PUBLIC;

        return $constant;
    }

    private function arityMethod(int $arity): ClassMethod
    {
        $method = new ClassMethod('getTypeArity');
        $method->flags = Modifiers::PUBLIC;
        $method->returnType = new Identifier('int');
        $method->stmts = [new Return_(new Int_($arity))];

        return $method;
    }

    private function variablesMethod(): ClassMethod
    {
        $method = new ClassMethod('getTypeVariables');
        $method->flags = Modifiers::PUBLIC;
        $method->returnType = new Identifier('array');
        $method->stmts = [new Return_(new FuncCall(
            new Name('typeArgumentsHeldBy'),
            [new Arg(new Variable('this'))]
        ))];

        return $method;
    }

    private function guardReturn(Return_ $node): ?Node
    {
        $signature = end($this->enclosing);

        if (!$signature instanceof Signature || $node->expr === null) {
            return null;
        }

        $node->expr = $this->guardValue($node->expr, $signature);

        return $node;
    }

    private function guardValue(Node\Expr $value, Signature $signature): Node\Expr
    {
        if ($signature->returnArguments === []) {
            return $value;
        }

        $arguments = [
            new Arg($value),
            new Arg($this->arguments($signature->returnArguments)),
            new Arg(new String_($signature->function)),
        ];

        if ($signature->needsOwner()) {
            $arguments[] = new Arg(new Variable('this'));
        }

        return new FuncCall(new Name('assertReturnTypeArguments'), $arguments);
    }

    /**
     * @return list<Expression>
     */
    private function guardParameters(Signature $signature): array
    {
        $guards = [];

        foreach ($signature->parameters as $parameter) {
            $guards[] = new Expression($parameter->variable === null
                ? $this->argumentsGuard($signature, $parameter)
                : $this->variableGuard($signature, $parameter));
        }

        return $guards;
    }

    private function argumentsGuard(Signature $signature, Parameter $parameter): FuncCall
    {
        $arguments = [
            new Arg(new Variable($parameter->name)),
            new Arg($this->arguments($parameter->arguments)),
            new Arg(new String_($signature->function)),
            new Arg(new Int_($parameter->position)),
            new Arg(new String_($parameter->name)),
        ];

        if ($signature->needsOwner()) {
            $arguments[] = new Arg(new Variable('this'));
        }

        return new FuncCall(new Name('assertTypeArguments'), $arguments);
    }

    /**
     * A parameter that is itself a type variable is not asked what it holds but
     * what it is, and only the object it was called on knows what that should
     * be.
     */
    private function variableGuard(Signature $signature, Parameter $parameter): FuncCall
    {
        return new FuncCall(new Name('assertTypeVariable'), [
            new Arg(new Variable($parameter->name)),
            new Arg(new String_((string) $parameter->variable)),
            new Arg(new Variable('this')),
            new Arg(new String_($signature->function)),
            new Arg(new Int_($parameter->position)),
            new Arg(new String_($parameter->name)),
        ]);
    }

    /**
     * @param list<string> $arguments
     */
    private function arguments(array $arguments): Array_
    {
        return new Array_(
            array_map(static fn (string $argument): ArrayItem => new ArrayItem(new String_($argument)), $arguments),
            ['kind' => Array_::KIND_SHORT]
        );
    }
}
