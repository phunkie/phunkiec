--TEST--
A typeclass erases to the interface PHP can hold it as
--FILE--
typeclass Invariant<F<_>>
{
    public function imap<A, B>((A) => B $f, (B) => A $g): F<B>;
}
--EXPECT--
<?php

interface Invariant
{
    public function imap($f, $g);
}
