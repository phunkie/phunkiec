--TEST--
A class that already answers for itself keeps its own answers
--FILE--
<?php

final class Stack<T> implements \Phunkie\Types\Kind
{
    public function getTypeArity(): int
    {
        return 1;
    }

    public function getTypeVariables(): array
    {
        return ["Int"];
    }
}
--EXPECT--
<?php

final class Stack implements \Phunkie\Types\Kind
{
    public function getTypeArity(): int
    {
        return 1;
    }

    public function getTypeVariables(): array
    {
        return ["Int"];
    }
    public const typeParameters = ['T'];
}
