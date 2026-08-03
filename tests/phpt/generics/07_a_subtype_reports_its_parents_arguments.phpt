--TEST--
A NonEmptyList parameter guards its arguments and leaves the constructor to PHP
--PENDING--
Generics are design only; see docs/generics.md
--FILE--
<?php

function head(NonEmptyList<Int> $numbers): Int {
    return $numbers->head;
}
--EXPECT--
<?php

function head(NonEmptyList $numbers): Int {
    assertTypeArguments($numbers, ['Int'], 'head', 1, 'numbers');
    return $numbers->head;
}
