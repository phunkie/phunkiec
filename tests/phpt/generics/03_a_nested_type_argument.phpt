--TEST--
A nested type argument is erased whole
--FILE--
<?php

function firstDefined(ImmList<Option<Int>> $options): Option<Int> {
    return $options->find(fn($o) => $o->isDefined());
}
--EXPECT--
<?php

function firstDefined(ImmList $options): Option {
    assertTypeArguments($options, ['Option<Int>'], 'firstDefined', 1, 'options');
    return assertReturnTypeArguments($options->find(fn($o) => $o->isDefined()), ['Int'], 'firstDefined');
}
