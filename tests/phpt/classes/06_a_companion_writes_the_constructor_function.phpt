--TEST--
A companion attribute writes the constructor function, ahead of a script's own code
--FILE--
#[Companion]
final class Coin(string $currency);
--EXPECT--
<?php

if (!function_exists('Coin')) {
    function Coin(string $currency): \Coin
    {
        return new \Coin($currency);
    }
}

#[Companion]
final class Coin
{
    public readonly string $currency;

    public function __construct(string $currency)
    {
        $this->currency = $currency;
    }
}
