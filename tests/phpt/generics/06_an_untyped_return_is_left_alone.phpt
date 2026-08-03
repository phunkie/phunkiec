--TEST--
A return type without a type argument gains no guard
--FILE--
<?php

function firstTwo(ImmList $numbers): ImmList {
    return $numbers->take(2);
}
--EXPECT--
<?php

function firstTwo(ImmList $numbers): ImmList {
    return $numbers->take(2);
}
