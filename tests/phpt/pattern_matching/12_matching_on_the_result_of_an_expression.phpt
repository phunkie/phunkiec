--TEST--
Matching on the result of an expression
--FILE--
$y = $response->status() match {
    200 => "ok",
    _ => "not ok"
};
--EXPECT--
<?php $y = (fn($on) => match (true) { $on(200) => "ok", $on(_) => "not ok", })(pmatch($response->status()));
