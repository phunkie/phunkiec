--TEST--
Guarding a case with a condition
--FILE--
$y = $option match {
    Some($v) if $v > 10 => "big",
    Some($v) => "small",
    _ => "none"
};
--EXPECT--
<?php $y = (fn($on) => match (true) { $on(\Phunkie\PatternMatching\Referenced\Some($v)) && $v > 10 => "big", $on(\Phunkie\PatternMatching\Referenced\Some($v)) => "small", $on(_) => "none", })(pmatch($option));
