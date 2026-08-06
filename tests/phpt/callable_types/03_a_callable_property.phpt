--TEST--
A callable property is erased where it was written, no declaration needed
--FILE--
<?php

final class Handler
{
    private (string) => bool $accepts;

    public function __construct(callable $accepts)
    {
        $this->accepts = $accepts;
    }
}
--EXPECT--
<?php

final class Handler
{
    private $accepts;

    public function __construct(callable $accepts)
    {
        $this->accepts = $accepts;
    }
}
