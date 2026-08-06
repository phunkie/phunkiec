--TEST--
A guard is still placed when the file also uses notation that is not PHP yet
--FILE--
<?php

function total(ImmList<Int> $numbers): Int {
    return 1;
}

for ($a <- ImmList(1, 2, 3)) { echo $a; }
--EXPECT--
<?php

function total(ImmList $numbers): Int {
    assertTypeArguments($numbers, ['Int'], 'total', 1, 'numbers');
    return 1;
}

ImmList(1, 2, 3)->withEach(function ($a) { echo $a; });
