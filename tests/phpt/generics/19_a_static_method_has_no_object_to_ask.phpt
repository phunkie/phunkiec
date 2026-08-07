--TEST--
A type its class bound is promised by no method, static or not
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

final class Stack
{
    public static function empty(): ImmList
    {
        return ImmList();
    }

    public function all(): ImmList
    {
        return ImmList();
    }
}
