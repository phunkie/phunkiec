--TEST--
Without an override, the bundled Some rule applies (control for the override test)
--FILE--
<?php

$y = $x match {
    Some($v) => $v
};
--EXPECT--
<?php

$y = (fn($on) => match (true) { $on(\Phunkie\PatternMatching\Referenced\Some($v)) => $v, })(pmatch($x));
