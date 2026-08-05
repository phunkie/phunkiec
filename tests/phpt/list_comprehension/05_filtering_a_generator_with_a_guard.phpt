--TEST--
Filtering a generator with a guard
--FILE--
for {
    $a <- ImmList(1, 2, 3) if $a % 2 == 0
} yield $a;
--EXPECT--
<?php

ImmList(1, 2, 3)->withFilter(function ($a) { return $a % 2 == 0; })->map(function ($a) { return $a; });
