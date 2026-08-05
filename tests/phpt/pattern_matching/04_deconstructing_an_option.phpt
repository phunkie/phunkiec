--TEST--
Deconstructing an Option
--FILE--
$y = $option match {
    Some($value) => $value + 1,
    None => 0
};
--EXPECT--
<?php

$y = (fn($on) => match (true) { $on(\Phunkie\PatternMatching\Referenced\Some($value)) => $value + 1, $on(None) => 0, })(pmatch($option));
