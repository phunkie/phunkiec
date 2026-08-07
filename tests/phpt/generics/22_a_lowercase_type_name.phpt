--TEST--
A type name written the way PHP spells it means the same type
--PENDING--
The compiler writes the reader's spelling through to the guard, which is right.
phunkie compares it letter for letter, so `array<string>` refuses an array of
strings that `array<String>` accepts. Fixed on phunkie's
normalise-promised-type-arguments branch, not in 1.4.1, which is what this
package resolves. This passes the day phunkie releases it, and then the marker
comes off.
--FILE--
<?php

function names(array<string> $users): array<string> {
    return $users;
}

echo implode(",", names(["ada", "alan"])), "\n";
--RUN--
ada,alan
