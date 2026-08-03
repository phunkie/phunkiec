--TEST--
An Option parameter and return guard their argument
--PENDING--
Generics are design only; see docs/generics.md
--FILE--
<?php

function getAddress(Option<User> $user): Option<Address> {
    return $user->map(fn($u) => $u->address());
}
--EXPECT--
<?php

function getAddress(Option $user): Option {
    assertTypeArguments($user, ['User'], 'getAddress', 1, 'user');
    return assertReturnTypeArguments($user->map(fn($u) => $u->address()), ['Address'], 'getAddress');
}
