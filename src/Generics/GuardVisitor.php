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

        return new FuncCall(new Name('assertReturnTypeArguments'), $arguments);
    }

    /**
     * @return list<Expression>
     */
    private function guardParameters(Signature $signature): array
    {
        $guards = [];

        // A parameter that is itself a type variable used to be guarded by
        // asking the object what it holds, through Kind. Kind is not part of
        // 2.0, so what a variable stands for is the checker's question now,
        // and only concrete promises are guarded when the code runs.
        foreach ($signature->parameters as $parameter) {
            if ($parameter->variable !== null) {
                continue;
            }

            $guards[] = new Expression($this->argumentsGuard($signature, $parameter));
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

        return new FuncCall(new Name('assertTypeArguments'), $arguments);
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
