--TEST--
Braces come back the moment there is something to put in them
--FILE--
final class Some<T>(T $value) extends Option<T>
{
    public function get()
    {
        return $this->value;
    }
}
--EXPECT--
<?php

final class Some extends Option
{
    public readonly mixed $value;

    public function __construct(mixed $value)
    {
        $this->value = $value;
    }

    public function get()
    {
        return $this->value;
    }
}
