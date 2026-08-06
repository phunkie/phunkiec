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

final class Box implements \Phunkie\Types\Kind
{
    private $value;

    public function put($item, $fallback, $either): int
    {
        assertTypeVariable($item, 'T', $this, 'Box::put', 1, 'item');
        return 1;
    }
    public const typeParameters = ['T'];
    public function getTypeArity(): int
    {
        return 1;
    }
    public function getTypeVariables(): array
    {
        return typeArgumentsHeldBy($this);
    }
}
