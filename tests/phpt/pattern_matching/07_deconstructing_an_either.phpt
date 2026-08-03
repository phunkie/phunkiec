--TEST--
Deconstructing an Either
--FILE--
$y = $either match {
    Right($a) => $a,
    Left($e) => $e->getMessage()
};
--EXPECT--
<?php $y = (fn($on) => match (true) { $on(\Phunkie\PatternMatching\Referenced\Right($a)) => $a, $on(\Phunkie\PatternMatching\Referenced\Left($e)) => $e->getMessage(), })(pmatch($either));
