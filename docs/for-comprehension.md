# For comprehensions

A for comprehension is syntax sugar for chaining `flatMap`, `map`, `withEach` and
`withFilter`. It is desugared at compile time, so there is no runtime cost, and it
works over any type that carries those methods — Option, Either, ImmList,
NonEmptyList, and effect's IO among them.

Every example below is the input on the left and the exact compiled output on the
right; they are taken from phunkiec's own test suite.

## Yielding — `flatMap` and `map`

A comprehension that `yield`s maps over its last generator and flatMaps every
generator before it.

```php
for {
    $a <- Some(42)
    $b <- Some($a + 1)
    $c <- Some($b + $a + 3)
} yield $c;
```

compiles to

```php
Some(42)->flatMap(function ($a) {
    return Some($a + 1)->flatMap(function ($b) use ($a) {
        return Some($b + $a + 3)->map(function ($c) use ($a, $b) {
            return $c;
        });
    });
});
```

Each closure inherits the variables bound before it through its `use (…)` clause.
A single generator with `yield` is just a `map`:

```php
for ($a <- ImmList(1, 2, 3)) yield $a + 1;
// ImmList(1, 2, 3)->map(function ($a) { return $a + 1; });
```

## Iterating — `withEach`

A comprehension with a statement body (no `yield`) iterates with `withEach`. Use
it for effects, where you don't want a result back.

```php
for ($a <- ImmList(1, 2, 3); $b <- ImmList($a); $c <- ImmList($b)) { echo $a + $b + $c; }
```

compiles to nested `withEach`:

```php
ImmList(1, 2, 3)->withEach(function ($a) {
    ImmList($a)->withEach(function ($b) use ($a) {
        ImmList($b)->withEach(function ($c) use ($a, $b) {
            echo $a + $b + $c;
        });
    });
});
```

The single-line `for (a <- m; b <- n)` form and the brace `for { a <- m \n b <- n }`
form are interchangeable.

## Guards — `withFilter`

An `if` filters the generator it follows, compiling to `withFilter`:

```php
for {
    $a <- ImmList(1, 2, 3) if $a % 2 == 0
} yield $a;
```

```php
ImmList(1, 2, 3)->withFilter(function ($a) {
    return $a % 2 == 0;
})->map(function ($a) {
    return $a;
});
```

A guard may also sit on its own line, where it filters the generator above it:

```php
for {
    $a <- ImmList(1, 2, 3)
    $b <- ImmList(1, 2, 3)
    if $b % 2 != 0
} yield ($a, $b);
```

> **Note on types.** A guard needs `withFilter`, which not every monad has. A lazy
> effect like IO has no value to filter, so guards over IO are unsupported — use
> the type's own combinators (e.g. effect's `ensure`) instead. Plain
> `for { $x <- io } yield …` works, since it only needs `flatMap` / `map`.

## Tuples

Bind a tuple by writing its parentheses on the left of `<-`; each part is unpacked
from the tuple by position:

```php
for {
    ($a) <- Some(Tuple(1))
    ($b, $c) <- Some(Pair(1, 2))
} yield $b;
```

```php
Some(Tuple(1))->flatMap(function ($t1) {
    $a = $t1->_1;
    return Some(Pair(1, 2))->map(function ($t2) use ($a) {
        $b = $t2->_1;
        $c = $t2->_2;
        return $b;
    });
});
```

`yield`ing a parenthesised list builds a tuple — a pair for two elements:

```php
for { $a <- Some(42); $b <- Some(43) } yield ($a, $b);
// … ->map(function ($b) use ($a) { return Pair($a, $b); });
```

## Wildcards

`_` binds nothing — it is for a generator whose value you don't need, and it never
joins the `use (…)` lists. Yielding `()` produces `Unit()`:

```php
for {
    $line <- IO\readline()
    _ <- IO\write($line, '/tmp/some_file.txt')
    _ <- IO\printLn("You have successfully written to file")
} yield ();
```

```php
IO\readline()->flatMap(function ($line) {
    return IO\write($line, '/tmp/some_file.txt')->flatMap(function ($_) use ($line) {
        return IO\printLn("You have successfully written to file")->map(function ($_) use ($line) {
            return Unit();
        });
    });
});
```
