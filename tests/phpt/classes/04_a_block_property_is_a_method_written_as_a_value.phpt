--TEST--
A block property compiles to the method it means, arms matching the object
--FILE--
class Validated {
    public Block $get = {
        Left($v)  => $v,
        Right($v) => $v
    };

    public Block $getOrElse = { $default =>
        Right($v) => $v,
        _         => $default
    };
}
--EXPECT--
<?php

class Validated {
    public function get()
    {
        return (fn($on) => match (true) { $on(\Phunkie\PatternMatching\Referenced\Left($v)) => $v, $on(\Phunkie\PatternMatching\Referenced\Right($v)) => $v, })(pmatch($this));
    }

    public function getOrElse($default)
    {
        return (fn($on) => match (true) { $on(\Phunkie\PatternMatching\Referenced\Right($v)) => $v, $on(_) => $default, })(pmatch($this));
    }
}
