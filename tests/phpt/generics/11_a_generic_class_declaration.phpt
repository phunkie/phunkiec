--TEST--
A class that declares its type parameters becomes a Kind
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

final class Stack implements \Phunkie\Types\Kind
{
    private array $items;

    public function __construct(...$items)
    {
        $this->items = $items;
    }
    public const typeParameters = ['T'];
    public function getTypeArity(): int
    {
        return 1;
    }
    public function getTypeVariables(): array
    {
        return typeArgumentsHeldBy($this);
    }
}

function deepen(Stack $stack): Stack
{
    assertTypeArguments($stack, ['Int'], 'deepen', 1, 'stack');
    return assertReturnTypeArguments($stack, ['Int'], 'deepen');
}
