--TEST--
A class that declares its type parameters compiles to a plain class
--FILE--
<?php

final class Stack<T>
{
    private array $items;

    public function __construct(...$items)
    {
        $this->items = $items;
    }
}

function deepen(Stack<Int> $stack): Stack<Int>
{
    return $stack;
}
--EXPECT--
<?php

final class Stack
{
    private array $items;

    public function __construct(...$items)
    {
        $this->items = $items;
    }
}

function deepen(Stack $stack): Stack
{
    assertTypeArguments($stack, ['Int'], 'deepen', 1, 'stack');
    return assertReturnTypeArguments($stack, ['Int'], 'deepen');
}
