--TEST--
Deconstructing a tuple by writing its parentheses
--FILE--
$y = $x match {
    ($a, $b) => $a + $b,
    ($a, $b, $c) => $a + $b + $c
};
--EXPECT--
<?php

$y = (fn($on) => match (true) { $on(\Phunkie\PatternMatching\Referenced\Pair($a, $b)) => $a + $b, $on(\Phunkie\PatternMatching\Referenced\Tuple($a, $b, $c)) => $a + $b + $c, })(pmatch($x));
