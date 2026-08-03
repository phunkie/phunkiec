--TEST--
A function taking a list of integers erases its type argument to a guard
--PENDING--
Generics are design only; see docs/generics.md
--FILE--
<?php

function doubleAll(ImmList<Int> $numbers): ImmList<Int> {
    return $numbers->map(fn($n) => $n * 2);
}
--EXPECT--
<?php

function doubleAll(ImmList $numbers): ImmList {
    assertTypeArguments($numbers, 'List<Int>', 'doubleAll', 1, 'numbers');
    return assertReturnTypeArguments($numbers->map(fn($n) => $n * 2), 'List<Int>', 'doubleAll');
}
