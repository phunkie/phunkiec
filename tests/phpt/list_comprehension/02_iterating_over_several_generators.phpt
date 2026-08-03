--TEST--
Iterating over several generators
--FILE--
for ($a <- ImmList(1, 2, 3); $b <- ImmList($a); $c <- ImmList($b)) { echo $a + $b + $c; }
--EXPECT--
<?php ImmList(1, 2, 3)->withEach(function ($a) { ImmList($a)->withEach(function ($b) use ($a) { ImmList($b)->withEach(function ($c) use ($a, $b) { echo $a + $b + $c; }); }); });
