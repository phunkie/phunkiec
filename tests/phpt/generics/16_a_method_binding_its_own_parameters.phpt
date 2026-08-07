--TEST--
A method binding its own type parameters compiles, and promises nothing
--FILE--
<?php

final class Stack<T>
{
    public function map<A, B>(A $a, ImmList<Int> $counts): ImmList<B>
    {
        return ImmList();
    }
}
--EXPECT--
<?php

final class Stack
{
    public function map($a, ImmList $counts): ImmList
    {
        assertTypeArguments($counts, ['Int'], 'Stack::map', 2, 'counts');
        return ImmList();
    }
}
