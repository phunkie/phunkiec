--TEST--
The guards do what they say when the compiled code is actually run
--FILE--
<?php

use Phunkie\Types\ImmList;

function doubleAll(ImmList<Int> $numbers): ImmList<Int> {
    return $numbers->map(fn($n) => $n * 2);
}

echo doubleAll(ImmList(1, 2, 3))->toString(), "\n";

try {
    doubleAll(ImmList("a", "b"));
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}
--RUN--
List(2, 4, 6)
doubleAll(): Argument #1 ($numbers) must be of type List<Int>, List<String> given
