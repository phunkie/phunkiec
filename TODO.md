# phunkiec Roadmap

The preprocessor compiles `.phunkie` files into plain PHP. Nothing happens at
runtime, so anything below has to be paid for at compile time or not at all.

## For-comprehension works for all phunkie monad types

The desugaring is already type-agnostic: it emits `flatMap`, `map`, `withEach`
and `withFilter`, and any type carrying those works. The question is which types
actually carry them.

- [ ] Audit which types have `flatMap` and `map`: Option, Either, Validation,
      ImmList, NonEmptyList, ImmSet, ImmMap, Function1, Id, IO, State, Reader,
      Kleisli, OptionT, EitherT, StateT, Free
- [ ] Audit which types have `withFilter` — a guard compiles to it, so a
      comprehension with an `if` only works on types that have one
- [ ] Audit which types have `withEach` — the `for (...) { ... }` iteration form
      compiles to it
- [ ] Decide what a guard means on a type with no `withFilter` (Option, Either):
      compile it away, refuse it at compile time, or add `withFilter` upstream
- [ ] Cover the same ground in phunkie/effect (IO), phunkie/streams (Stream),
      phetch and http4p
- [ ] A feature scenario per type, compiled *and run*, not just token-compared

## Pattern matching works for all relevant phunkie type classes

phunkie 1.2.0 ships referenced patterns for its own types, and phunkiec maps the
surface syntax onto them. The other libraries have none.

- [ ] effect: referenced patterns for IO and friends, then macros to reach them
- [ ] streams: referenced patterns for Stream
- [ ] phetch, http4p: same
- [ ] Decide how a library ships its own patterns. Right now every rule lives in
      phunkiec's `macros/`, which does not scale across repos: a package should
      be able to ship its own `.syn` macros and have phunkiec find them
- [ ] Lift the arity cap: `Tuple` and `ImmSet` patterns stop at three parts,
      because each arity needs its own rule
- [ ] Compile-time exhaustiveness ("sealed" cases). Macros rewrite, they do not
      analyse, so this is not a macro. Today a non-exhaustive match throws
      `UnhandledMatchError` at runtime, from `match (true)`

## Generics

Decided and being specified in [GENERICS_TODO.md](GENERICS_TODO.md): angle
brackets, erased at compile time, with a guard where PHP cannot express the
constraint itself. Design in [docs/generics.md](docs/generics.md).

## Nullary constructors written without parentheses

`None` and `Nil` are objects, not types. Scala lets you write them bare, and so
should a `.phunkie` file:

```php
$maybe = None;
$empty = Nil;
```

- [ ] Rewrite a bare `None` to `None()` and a bare `Nil` to `Nil()`
- [ ] Only where the name stands as a value. `None` inside a pattern is already
      handled by the pattern matching macros, and a class or constant that
      happens to be called `None` must be left alone
- [ ] Decide whether this extends to `Unit`, which has the same shape

## Destructuring assignment

```php
($a, $b) = functionThatReturnsAPair();
```

- [ ] Compile to a fresh variable holding the pair, then `$a = $t->_1; $b = $t->_2;`
- [ ] Works for tuples of any size, and for `Pair`
- [ ] Decide what happens when the right hand side is not a tuple: silence, or a
      compile-time error

## Pipe operator

PHP 8.5 introduces `|>`. For 8.2 to 8.4 the preprocessor can supply it, so that
`.phunkie` files can use it everywhere.

- [ ] `$x |> $f` compiles to `$f($x)`
- [ ] Chains: `$x |> $f |> $g` compiles to `$g($f($x))`
- [ ] Works with `Function1`, and with anything callable
- [ ] On PHP 8.5, decide whether to emit `|>` untouched or keep compiling it away,
      so that one `.phunkie` file compiles for every supported version
- [ ] Target phunkie 2.0

Syn can already express this: an infix macro may open with a capture, which is
what `$(layer() as left) |> $(layer() as right)` needs.

## Known gaps

- [ ] The iteration form, `for (...) { ... }`, only supports plain bindings — no
      guards, tuples or wildcards. An unsupported one leaks `__each(...)` into the
      output as invalid PHP instead of failing with an error
- [ ] A macro that matches nothing should fail the compile, not emit its own
      internal forms as if they were code
- [ ] phunkiec has no README. The syntax is only described by its feature files
- [ ] syn's `docs/macro-dsl.md` documents captures that do not exist, and none of
      what it gained: multi-line macros, expansion to a fixpoint, clause and token
      captures, infix macros, fresh variables
