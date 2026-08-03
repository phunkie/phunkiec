# Generics Roadmap

PHP has no generics. phunkiec adds the notation and erases it, so what comes out
is the PHP you would have written by hand plus a guard where one is needed.

Design lives in [docs/generics.md](docs/generics.md), written before the code.
Each section becomes a `.phpt` fixture under `tests/phpt/generics/`, marked
`--PENDING--` until it compiles. A pending fixture asserts the feature is *still*
missing, so the day it lands the fixture fails and the marker has to be removed.

## Decisions taken

- [x] **Angle brackets** — `ImmList<Int>`, matching what `showType()` already
      prints and what every other language with generics uses.
- [x] **Erasure, not reification** — type arguments vanish at compile time. The
      native `ImmList` declaration is left to PHP, which already enforces it;
      only the part PHP cannot express becomes a guard.
- [x] **Guards go in the body, not at the call site** — so they hold however the
      value arrived: literal, variable, or return value. One error channel
      rather than two.
- [x] **`Nothing` accepts, `Mixed` refuses** — `List<Nothing>` is the empty list
      and satisfies every `List<A>`; `List<Mixed>` is heterogeneous and satisfies
      none. They read as opposites because they are.
- [x] **`Float`, not `Double`** — `Double` existed only because `gettype()`
      returns the legacy `"double"`. Standardised in phunkie so the guard and
      `showType()` agree.
- [x] **The `None` singleton stays** — there is exactly one `None` object, so it
      cannot carry a type argument. The type is static, supplied by the
      signature, never by the value.
- [x] **The guard compares type arguments, not rendered type names** — via
      `getTypeVariables()`, which every `Kind` already answers. The constructor
      is PHP's business; the arguments are ours. Comparing whole names would
      have broken the first subtype it met: `NonEmptyList` reports itself as
      `List<Int>`, so a guard looking for `NonEmptyList<Int>` would never match.
      Arity greater than one falls out for free.
- [x] **`Nil` is a value, not a type** — like `None`, so there is no `Nil<T>`.
      An empty list reports `Nothing` for its argument and the rule above
      accepts it.
- [x] **`NonEmptyList<Int>`, not `Nel<Int>`** — the class is `NonEmptyList` and
      `Nel()` is only a factory function, so the full name is what a signature
      can name. This is what cats does too.

## Specified

Written up in the docs and pinned by a fixture. Nothing here compiles yet: the
fixtures are `--PENDING--` except where noted.

- [x] `.phpt` fixtures grouped by feature, with the `--PENDING--` mechanism
- [x] **Parameters** — a function taking `ImmList<Int>`, what it erases to, and
      the error
- [x] **Nested type arguments** — `ImmList<Option<Int>>` erased whole
- [x] **Return types** — every `return` guarded, not only the last; the empty
      case falls out of the `Nothing` rule and needs no special handling
- [x] **Subtypes** — `NonEmptyList<Int>` guards the same argument as
      `ImmList<Int>` and leaves non-emptiness to PHP's own declaration
- [x] A parameter with no type argument is left alone — *passes already*
- [x] A return type with no type argument is left alone — *passes already*

## Implementation

Nothing below is started. This is the whole of the actual work.

- [ ] Parse `<...>` in a parameter type and erase it
- [ ] Parse `<...>` in a return type and erase it
- [ ] Emit the parameter guard at the top of the body
- [ ] Wrap every `return` expression in the return guard
- [ ] `assertTypeArguments` / `assertReturnTypeArguments` themselves, including
      the `Nothing` accepts / `Mixed` refuses rules. Both are named
      aspirationally in the docs and do not exist. The closest existing thing is
      `assertSameTypeAsCollectionType()` in phunkie, which compares a value
      against a collection rather than a collection against a named type.
- [ ] Decide where the guards live: phunkie, or a runtime shipped with phunkiec

## Next to specify

- [ ] **`Option<T>` parameters** — `function getAddress(Option<User> $user)`.
      Where `None` assignability gets decided, and the reason the singleton
      decision above matters.
- [ ] **Two type parameters** — `ImmMap<String, User>`, so arity > 1 is
      exercised before anything harder.

## Later

- [ ] **User-defined generic classes** — `class Stack<T>`. Deliberately last:
      `ImmList<Int>` works because the type argument is recoverable from the
      value, and a user's `Stack<T>` has nothing to inspect. Needs declaration
      syntax, type parameter scoping and variance all at once.
- [ ] **Generic functions** — `function first<A>(ImmList<A> $xs): Option<A>`,
      where the type variable is bound by the signature rather than supplied.
- [ ] **Higher-kinded types** — `Functor<F>`. Parked; see the `Kind` interface
      in phunkie. Too early.

## Open questions

- [ ] Compile-time rejection as well as runtime. `doubleAll(ImmList("a"))` has
      the literal right there, so phunkiec *could* refuse it without running.
      Tempting, but it only catches the easy case and gives two error channels
      to keep consistent.
- [ ] Whether `features/` should keep its transformation scenarios now that
      `.phpt` covers them byte-exactly. The features run the real binary through
      `exec()`, so they are not redundant as a layer, but the 22 scenarios in
      them are.
