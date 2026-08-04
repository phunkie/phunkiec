--TEST--
Every return in the body is guarded, not only the last
--FILE--
<?php

function firstTwo(ImmList<Int> $numbers): ImmList<Int> {
    if ($numbers->isEmpty()) {
        return ImmList();
    }

    return $numbers->take(2);
}
--EXPECT--
<?php

function firstTwo(ImmList $numbers): ImmList {
    assertTypeArguments($numbers, ['Int'], 'firstTwo', 1, 'numbers');
    if ($numbers->isEmpty()) {
        return assertReturnTypeArguments(ImmList(), ['Int'], 'firstTwo');
    }

    return assertReturnTypeArguments($numbers->take(2), ['Int'], 'firstTwo');
}
