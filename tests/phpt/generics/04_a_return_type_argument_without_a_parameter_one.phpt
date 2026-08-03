--TEST--
A return type argument is guarded even when no parameter has one
--PENDING--
Generics are design only; see docs/generics.md
--FILE--
<?php

function parseAll(array $rows): ImmList<Int> {
    return ImmList(...array_map('intval', $rows));
}
--EXPECT--
<?php

function parseAll(array $rows): ImmList {
    return assertReturnTypeArguments(ImmList(...array_map('intval', $rows)), ['Int'], 'parseAll');
}
