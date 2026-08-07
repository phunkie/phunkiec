--TEST--
A type variable erases wherever its class declared it, and promises nothing
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

final class Stack
{
    private array $items;

    public function push($item): Stack
    {
        return new Stack();
    }

    public function all(): ImmList
    {
        return ImmList(...$this->items);
    }
}
