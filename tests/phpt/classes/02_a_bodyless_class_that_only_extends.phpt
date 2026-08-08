--TEST--
A bodyless class with nothing of its own is a head and a semicolon
--FILE--
final class None extends Option;
--EXPECT--
<?php

final class None extends Option
{
}
