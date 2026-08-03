--TEST--
Deconstructing a Validation
--FILE--
$y = $validation match {
    Success($a) => $a,
    Failure($e) => $e->getMessage()
};
--EXPECT--
<?php $y = (fn($on) => match (true) { $on(\Phunkie\PatternMatching\Referenced\Success($a)) => $a, $on(\Phunkie\PatternMatching\Referenced\Failure($e)) => $e->getMessage(), })(pmatch($validation));
