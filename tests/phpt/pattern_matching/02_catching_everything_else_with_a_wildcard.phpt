--TEST--
Catching everything else with a wildcard
--FILE--
$y = $x match {
    1 => "one",
    2 => "two",
    _ => "many"
};
--EXPECT--
<?php

$y = (fn($on) => match (true) { $on(1) => "one", $on(2) => "two", $on(_) => "many", })(pmatch($x));
