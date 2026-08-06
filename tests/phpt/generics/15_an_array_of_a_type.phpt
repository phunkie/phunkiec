--TEST--
An array says what it holds, and PHP still says it is an array
--FILE--
<?php

function names(array<User> $users): array<string> {
    return array_map(fn($u) => $u->name, $users);
}
--EXPECT--
<?php

function names(array $users): array {
    assertTypeArguments($users, ['User'], 'names', 1, 'users');
    return assertReturnTypeArguments(array_map(fn($u) => $u->name, $users), ['string'], 'names');
}
