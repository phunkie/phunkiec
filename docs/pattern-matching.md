# Pattern matching

`value match { … }` checks a value against a series of patterns. It is desugared
onto phunkie's `pmatch`, so the subject is evaluated once and each case is asked in
turn whether it matches. A case can also deconstruct the value, binding its parts.

Every example is the input on the left and the exact compiled output on the right,
taken from phunkiec's test suite.

## The shape of a match

```php
$y = $x match {
    1 => "one",
    2 => "two"
};
```

compiles to a native `match (true)` where each case is guarded by `$on`:

```php
$y = (fn($on) => match (true) {
    $on(1) => "one",
    $on(2) => "two",
})(pmatch($x));
```

The subject is bound once to `$on = pmatch($x)`, inside a closure, so it is
evaluated a single time however many cases there are — and so a case is free to
deconstruct it.

## Wildcard

`_` is the catch-all. It needs no special rule — `pmatch` reads `_` as "matches
anything":

```php
$y = $x match {
    1 => "one",
    _ => "many"
};
// … $on(1) => "one", $on(_) => "many", …
```

There is no exhaustiveness check at compile time; a match that falls through
throws PHP's `\UnhandledMatchError` at runtime, from the underlying `match (true)`.

## Guards

A case may carry an `if` guard, asked only after its pattern matches — so it can
read what the pattern bound:

```php
$y = $option match {
    Some($v) if $v > 10 => "big",
    Some($v) => "small",
    _ => "none"
};
```

```php
$y = (fn($on) => match (true) {
    $on(\Phunkie\PatternMatching\Referenced\Some($v)) && $v > 10 => "big",
    $on(\Phunkie\PatternMatching\Referenced\Some($v)) => "small",
    $on(_) => "none",
})(pmatch($option));
```

## Deconstruction

Writing a constructor with variables inside binds its parts. The constructor is
rewritten to phunkie's referenced pattern, so no `use function` import is needed:

```php
$y = $option match {
    Some($value) => $value + 1,
    None => 0
};
// $on(\Phunkie\PatternMatching\Referenced\Some($value)) => $value + 1, …
```

A **value** or a **wildcard** inside the constructor is left alone — only a bare
variable is a binding — so all three coexist:

```php
$option match {
    Some(42) => "the answer",   // matched by value
    Some(_)  => "some other",   // matched by wildcard
    None     => "none"
};
```

Supported out of the box: `Some`, `Right`/`Left`, `Success`/`Failure`, `Pair`,
`Tuple`, `Nel`, `Cons`, `ImmSet`, `Id`, `ImmString`, `ImmInteger`, `Function1`,
`State`, `Reader`, `Kleisli`, `OptionT`, `EitherT`, `StateT`.

### Lists

`ImmList($x, $xs)` deconstructs a list into head and tail; the tail decides which
supporting function is meant — `Nil` means nothing follows the head:

```php
$total = $list match {
    Nil => 0,
    ImmList($x, Nil) => $x,
    ImmList($x, $xs) => $x + total($xs)
};
```

```php
$total = (fn($on) => match (true) {
    $on(Nil) => 0,
    $on(\Phunkie\PatternMatching\Referenced\ListNoTail($x, Nil)) => $x,
    $on(\Phunkie\PatternMatching\Referenced\ListWithTail($x, $xs)) => $x + total($xs),
})(pmatch($list));
```

A map has no pattern of its own — match it through its list of pairs with
`$map->toList()`.

### Tuples by parentheses

A parenthesised list of variables is a tuple pattern; two elements are a `Pair`:

```php
$y = $x match {
    ($a, $b) => $a + $b,
    ($a, $b, $c) => $a + $b + $c
};
// $on(\Phunkie\PatternMatching\Referenced\Pair($a, $b)) => …
// $on(\Phunkie\PatternMatching\Referenced\Tuple($a, $b, $c)) => …
```

## Types from other packages

A library can ship pattern rules for its own types (see the
[README](../README.md)). For example, with `phunkie/effect` installed, an effect
`IO` deconstructs to its thunk:

```php
$result = $io match {
    IO($thunk) => $thunk()
};
// $on(\Phunkie\Effect\PatternMatching\Referenced\IO($thunk)) => $thunk()
```

Where a name collides — effect's `IO` and phunkie's own `Cats\IO` — the installed
package's rule wins.
