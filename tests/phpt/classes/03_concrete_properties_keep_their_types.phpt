--TEST--
A primary constructor's concrete types reach PHP, on property and parameter
--FILE--
class Account(Balance $balance, AccountHolder $holder);
--EXPECT--
<?php

class Account
{
    public readonly Balance $balance;
    public readonly AccountHolder $holder;

    public function __construct(Balance $balance, AccountHolder $holder)
    {
        $this->balance = $balance;
        $this->holder = $holder;
    }
}
