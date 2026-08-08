--TEST--
A bodyless class states what it is made of, and the compiler writes the rest
--FILE--
final class Some<T>(T $value) extends Option<T>;
--EXPECT--
<?php

final class Some extends Option
{
    public readonly mixed $value;

    public function __construct(mixed $value)
    {
        $this->value = $value;
    }
}
