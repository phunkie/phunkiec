<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeVisitorAbstract;

/**
 * Puts a guard where a type argument used to be.
 *
 * Guards need to know which function a `return` belongs to, which is the whole
 * reason this is a tree and not another pass over tokens: a rule matching
 * `return` by shape would also wrap the one inside a nested closure. Here the
 * enclosing function is on a stack, and a closure pushes nothing to guard
 * against, so a `return` inside one is left alone without a rule saying so.
 */
final class GuardVisitor extends NodeVisitorAbstract
{
    /**
     * @var list<Signature|null>
     */
    private array $enclosing = [];

    public function __construct(
        private readonly Signatures $signatures,
    ) {
    }

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof Function_ || $node instanceof ClassMethod) {
            $this->enclosing[] = $this->signatures->forFunction($node->name->toString());

            return null;
        }

        if ($node instanceof Closure || $node instanceof ArrowFunction) {
            $this->enclosing[] = null;
        }

        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof Closure || $node instanceof ArrowFunction) {
            array_pop($this->enclosing);

            return null;
        }

        if ($node instanceof Return_) {
            return $this->guardReturn($node);
        }

        if (!($node instanceof Function_ || $node instanceof ClassMethod)) {
            return null;
        }

        $signature = array_pop($this->enclosing);

        if ($signature === null || $node->stmts === null) {
            return null;
        }

        $node->stmts = array_merge($this->guardParameters($signature), $node->stmts);

        return $node;
    }

    private function guardReturn(Return_ $node): ?Node
    {
        $signature = end($this->enclosing);

        if (!$signature instanceof Signature || $signature->returnArguments === [] || $node->expr === null) {
            return null;
        }

        $node->expr = new FuncCall(new Name('assertReturnTypeArguments'), [
            new Arg($node->expr),
            new Arg($this->arguments($signature->returnArguments)),
            new Arg(new String_($signature->function)),
        ]);

        return $node;
    }

    /**
     * @return list<Expression>
     */
    private function guardParameters(Signature $signature): array
    {
        $guards = [];

        foreach ($signature->parameters as $parameter) {
            $guards[] = new Expression(new FuncCall(new Name('assertTypeArguments'), [
                new Arg(new Variable($parameter->name)),
                new Arg($this->arguments($parameter->arguments)),
                new Arg(new String_($signature->function)),
                new Arg(new Int_($parameter->position)),
                new Arg(new String_($parameter->name)),
            ]));
        }

        return $guards;
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
