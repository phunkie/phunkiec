--TEST--
A type argument is erased whatever its name is qualified with
--FILE--
<?php

function total(Phunkie\Types\ImmList<Int> $numbers): Phunkie\Types\ImmList<Int> {
    return $numbers;
}
--EXPECT--
<?php

function total(Phunkie\Types\ImmList $numbers): Phunkie\Types\ImmList {
    assertTypeArguments($numbers, ['Int'], 'total', 1, 'numbers');
    return assertReturnTypeArguments($numbers, ['Int'], 'total');
}
