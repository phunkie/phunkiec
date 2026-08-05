--TEST--
Destructuring tuples in a generator
--FILE--
for {
    ($a) <- Some(Tuple(1))
    ($b, $c) <- Some(Pair(1, 2))
} yield $b;
--EXPECT--
<?php

Some(Tuple(1))->flatMap(function ($t1) { $a = $t1->_1; return Some(Pair(1, 2))->map(function ($t2) use ($a) { $b = $t2->_1; $c = $t2->_2; return $b; }); });
