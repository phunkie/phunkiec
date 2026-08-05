--TEST--
A closure is guarded like a named function
--FILE--
<?php

use Phunkie\Types\ImmList;

$doubleAll = function (ImmList<Int> $numbers): ImmList<Int> {
    return $numbers->map(fn($n) => $n * 2);
};
--EXPECT--
<?php

use Phunkie\Types\ImmList;

$doubleAll = function (ImmList $numbers): ImmList {
    assertTypeArguments($numbers, ['Int'], '{closure}', 1, 'numbers');
    return assertReturnTypeArguments($numbers->map(fn($n) => $n * 2), ['Int'], '{closure}');
};
