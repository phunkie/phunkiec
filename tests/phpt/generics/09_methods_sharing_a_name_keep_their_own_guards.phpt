--TEST--
Two methods with the same name keep their own guards
--FILE--
<?php

use Phunkie\Types\ImmList;

class Years
{
    public function all(ImmList<Int> $xs): ImmList<Int>
    {
        return $xs;
    }
}

class Names
{
    public function all(ImmList<String> $xs): ImmList<String>
    {
        return $xs;
    }
}
--EXPECT--
<?php

use Phunkie\Types\ImmList;

class Years
{
    public function all(ImmList $xs): ImmList
    {
        assertTypeArguments($xs, ['Int'], 'Years::all', 1, 'xs');
        return assertReturnTypeArguments($xs, ['Int'], 'Years::all');
    }
}

class Names
{
    public function all(ImmList $xs): ImmList
    {
        assertTypeArguments($xs, ['String'], 'Names::all', 1, 'xs');
        return assertReturnTypeArguments($xs, ['String'], 'Names::all');
    }
}
