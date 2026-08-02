--TEST--
A --macro-file rule overrides a bundled rule of the same shape
--MACRO-FILE--
$(macro) {
    $on( Some( $(T_VARIABLE as value) ) )
} >> {
    $on(SHADOWED($(value)))
}
--FILE--
<?php

$y = $x match {
    Some($v) => $v
};
--EXPECT--
<?php

$y = (fn($on) => match (true) { $on(SHADOWED($v)) => $v, })(pmatch($x));
