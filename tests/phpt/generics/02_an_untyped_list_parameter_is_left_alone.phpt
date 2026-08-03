--TEST--
A parameter without a type argument gains no guard
--FILE--
<?php

function count(ImmList $numbers): Int {
    return $numbers->length;
}
--EXPECT--
<?php

function count(ImmList $numbers): Int {
    return $numbers->length;
}
