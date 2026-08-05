--TEST--
A source that never opens a tag is still PHP
--FILE--
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
