--TEST--
A simpler switch statement
--FILE--
$y = $x match {
    1 => "one",
    2 => "two"
};
--EXPECT--
<?php

$y = (fn($on) => match (true) { $on(1) => "one", $on(2) => "two", })(pmatch($x));
