--TEST--
A property whose default is a block is a Block, the word unwritten
--FILE--
final class Box<T>(T $value)
{
    public $unwrapped = { Box($v) => $v };
}
--EXPECT--
<?php

final class Box
{
    public readonly mixed $value;

    public function __construct(mixed $value)
    {
        $this->value = $value;
    }

    public function unwrapped()
    {
        return (fn($on) => match (true) { $on(Box($v)) => $v, })(pmatch($this));
    }
}
