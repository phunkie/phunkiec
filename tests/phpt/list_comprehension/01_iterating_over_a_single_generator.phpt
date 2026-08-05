--TEST--
Iterating over a single generator
--FILE--
for ($a <- ImmList(1, 2, 3)) { echo $a; }
--EXPECT--
<?php

ImmList(1, 2, 3)->withEach(function ($a) { echo $a; });
