--TEST--
Yielding from a single generator maps over it
--FILE--
for ($a <- ImmList(1, 2, 3)) yield $a + 1;
--EXPECT--
<?php ImmList(1, 2, 3)->map(function ($a) { return $a + 1; });
