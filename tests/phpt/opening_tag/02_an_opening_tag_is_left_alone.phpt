--TEST--
A source that opens its own tag is not given a second
--FILE--
<?php

function twice(int $n): int
{
    return $n * 2;
}
--EXPECT--
<?php

function twice(int $n): int
{
    return $n * 2;
}
