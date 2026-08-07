--TEST--
An array is read by what it holds, however its type name is spelled
--FILE--
<?php

function names(array<string> $users): array<string> {
    return $users;
}

function keptNames(array<int, string> $users): array<string> {
    return array_filter($users, fn($n) => $n !== "");
}

function bornIn(array<string, int> $years): array<string, int> {
    return $years;
}

echo implode(",", names(["ada", "alan"])), "\n";
echo implode(",", keptNames(["ada", "", "alan"])), "\n";
echo implode(",", array_keys(bornIn(["ada" => 1815]))), "\n";

try {
    names([1, 2]);
} catch (TypeError $e) {
    echo $e->getMessage(), "\n";
}
--RUN--
ada,alan
ada,alan
ada
names(): Argument #1 ($users) must be of type Array<string>, Array<Int> given
