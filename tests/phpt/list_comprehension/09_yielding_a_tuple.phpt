--TEST--
Yielding a tuple
--FILE--
for {
    $a <- Some(42)
    $b <- Some(43)
} yield ($a, $b);
--EXPECT--
<?php Some(42)->flatMap(function ($a) { return Some(43)->map(function ($b) use ($a) { return Pair($a, $b); }); });
