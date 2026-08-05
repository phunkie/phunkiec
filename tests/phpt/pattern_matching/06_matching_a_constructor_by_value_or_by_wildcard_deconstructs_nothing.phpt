--TEST--
Matching a constructor by value or by wildcard deconstructs nothing
--FILE--
$y = $option match {
    Some(42) => "the answer",
    Some(_) => "some other",
    None => "none"
};
--EXPECT--
<?php

$y = (fn($on) => match (true) { $on(Some(42)) => "the answer", $on(Some(_)) => "some other", $on(None) => "none", })(pmatch($option));
