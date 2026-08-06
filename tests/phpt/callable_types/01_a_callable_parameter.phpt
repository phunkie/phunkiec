--TEST--
A callable parameter is erased, PHP having nothing it could enforce
--FILE--
<?php

function apply((int) => string $f, int $n): string {
    return $f($n);
}
--EXPECT--
<?php

function apply($f, int $n): string {
    return $f($n);
}
