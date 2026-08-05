--TEST--
The notation phunkiec adds works without an opening tag too
--FILE--
function describe($option): string
{
    return $option match {
        Some($value) => "got " . $value,
        None => "nothing"
    };
}
--EXPECT--
<?php

function describe($option): string
{
    return (fn($on) => match (true) { $on(\Phunkie\PatternMatching\Referenced\Some($value)) => "got " . $value, $on(None) => "nothing", })(pmatch($option));
}
