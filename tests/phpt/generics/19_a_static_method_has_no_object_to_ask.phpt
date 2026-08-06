--TEST--
A static method promises nothing about a type its class bound, having no object
--FILE--
<?php

final class Stack<T>
{
    public static function empty(): ImmList<T>
    {
        return ImmList();
    }

    public function all(): ImmList<T>
    {
        return ImmList();
    }
}
--EXPECT--
<?php

final class Stack implements \Phunkie\Types\Kind
{
    public static function empty(): ImmList
    {
        return ImmList();
    }

    public function all(): ImmList
    {
        return assertReturnTypeArguments(ImmList(), ['T'], 'Stack::all', $this);
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
