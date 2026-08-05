--TEST--
A method may use the type variables its class declared
--FILE--
<?php

use Phunkie\Types\ImmList;

final class Stack<T>
{
    private array $items;

    public function push(T $item): Stack<T>
    {
        return new Stack();
    }

    public function all(): ImmList<T>
    {
        return ImmList(...$this->items);
    }
}
--EXPECT--
<?php

use Phunkie\Types\ImmList;

final class Stack implements \Phunkie\Types\Kind
{
    private array $items;

    public function push($item): Stack
    {
        assertTypeVariable($item, 'T', $this, 'Stack::push', 1, 'item');
        return assertReturnTypeArguments(new Stack(), ['T'], 'Stack::push', $this);
    }

    public function all(): ImmList
    {
        return assertReturnTypeArguments(ImmList(...$this->items), ['T'], 'Stack::all', $this);
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
