--TEST--
A callable return type takes its colon with it
--FILE--
<?php

function adder(int $n): (int) => int {
    return fn($m) => $m + $n;
}
--EXPECT--
<?php

function adder(int $n) {
    return fn($m) => $m + $n;
}
