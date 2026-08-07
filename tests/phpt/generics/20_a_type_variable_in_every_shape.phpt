--TEST--
A type variable goes wherever it is written, not only where it stands alone
--FILE--
<?php

final class Box<T>
{
    private T $value;

    public function put(T $item, ?T $fallback, T|int $either): int
    {
        return 1;
    }
}
--EXPECT--
<?php

final class Box
{
    private $value;

    public function put($item, $fallback, $either): int
    {
        return 1;
    }
}
