--TEST--
Deconstructing a list
--FILE--
$total = $list match {
    Nil => 0,
    ImmList($x, Nil) => $x,
    ImmList($x, $xs) => $x + total($xs)
};
--EXPECT--
<?php

$total = (fn($on) => match (true) { $on(Nil) => 0, $on(\Phunkie\PatternMatching\Referenced\ListNoTail($x, Nil)) => $x, $on(\Phunkie\PatternMatching\Referenced\ListWithTail($x, $xs)) => $x + total($xs), })(pmatch($list));
