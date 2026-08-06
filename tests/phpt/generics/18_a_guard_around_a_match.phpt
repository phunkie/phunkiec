--TEST--
A guard is still placed when the notation that is not PHP yet is in its own body
--FILE--
<?php

function describe(ImmList<Int> $numbers): String {
    return $numbers match {
        1 => "one",
        2 => "two"
    };
}
--EXPECT--
<?php

function describe(ImmList $numbers): String {
    assertTypeArguments($numbers, ['Int'], 'describe', 1, 'numbers');
    return (fn($on) => match (true) { $on(1) => "one", $on(2) => "two", })(pmatch($numbers));
}
