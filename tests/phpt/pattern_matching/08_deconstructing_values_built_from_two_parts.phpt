--TEST--
Deconstructing values built from two parts
--FILE--
$y = $x match {
    Pair($a, $b) => $a + $b,
    Nel($head, $tail) => $head,
    Cons($head, $tail) => $head,
    ImmSet($a, $b) => $a + $b
};
--EXPECT--
<?php $y = (fn($on) => match (true) { $on(\Phunkie\PatternMatching\Referenced\Pair($a, $b)) => $a + $b, $on(\Phunkie\PatternMatching\Referenced\Nel($head, $tail)) => $head, $on(\Phunkie\PatternMatching\Referenced\Cons($head, $tail)) => $head, $on(\Phunkie\PatternMatching\Referenced\ImmSet($a, $b)) => $a + $b, })(pmatch($x));
