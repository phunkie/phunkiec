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
- [x] **`Option<T>`** — `Some` reports what it holds; `None` cannot, being one
      shared object, so its type comes from the signature. It satisfies every
      `Option<A>` under the rule that already accepts an empty list, so there is
      no new rule to write
- [x] A parameter with no type argument is left alone — *passes already*
- [x] A return type with no type argument is left alone — *passes already*

## Implementation

The guards exist. Emitting them does not.

- [x] **The guards live in phunkie**, in `Functions/assertion.php`. After
      transpiling, phunkiec is not in the build and phunkie is, so compiled code
      can only call into phunkie.
- [x] **`assertTypeArguments` / `assertReturnTypeArguments`**, with the
      `Nothing` accepts and `Mixed` refuses rules. They are global, like
      `pmatch`, because compiled code calls them unqualified from whatever
      namespace the source was written in. They throw `TypeError` rather than
      returning a `Validation`, which is what `assertSameTypeAsCollectionType`
      beside them does, because compiled code calls the first as a statement and
      wraps a `return` in the second, and neither position would read a result.
      Verified to produce the errors in the docs word for word.
- [x] Parse `<...>` in a parameter type and erase it
- [x] Parse `<...>` in a return type and erase it
- [x] Emit the parameter guard at the top of the body
- [x] Wrap every `return` expression in the return guard

Seven of the eight fixtures pass. Verified end to end as well: a compiled source
run against phunkie raises the errors in the docs, on 8.2 through 8.5.

### How it works

Three passes, in `PhunkieProcessor::processFile`. Erasure comes first because a
type argument is not PHP and nothing can parse the file until the brackets are
gone, which also makes it the only pass that can read them: what they said is
carried on in `Signatures`. Macros run next. Guards go in last, on a tree, and
they have to: placing one means knowing which function a `return` sits in, and
the tree has to be of the code as it will finally be, macros and all.

`GuardVisitor` keeps the enclosing function on a stack, so a `return` inside a
nested closure is left alone without a rule saying so, the closure having
pushed nothing to guard against. The printing preserves the original formatting,
so a file only changes where a guard went in.

## Still to do

- [x] **Nested arguments**, fixture 03. `T_SR` is split back into the two
      brackets it stands for, but only inside a group, so `$bits = 8 >> 2` is
      still a shift. Splitting a parameter list counts angle brackets too, since
      the comma in `ImmList<ImmMap<String, Int>> $rows` belongs to the map, and
      stops counting after a `=`, where a `<` is a comparison. No fixture is
      pending now.

- [x] **A nested argument names a class, and the value reports a type.**
      A signature names `ImmMap` because the class is what PHP enforces, while
      the value calls itself `Map`. At the top level it never showed, the guard
      taking the constructor from the value and comparing only the arguments,
      but one level down the written text is the whole of what is compared, so
      `ImmList<ImmMap<String, Int>>` promised something no value could report.

      Fixed in phunkie, where `kind` has held the mapping since #41:
      `asTypeNames` reads what a signature promised in the names values answer
      in, at any depth, before anything is compared or rendered.


- [x] **Closures** are guarded like anything else. What a signature promised
      now travels on the declaration itself, as an attribute erasure writes and
      the guard pass reads, so nothing is looked up by name and a closure needs
      no name. `Signatures` is gone with it.
- [x] **A syntax check on the output.** Both entries above used to present
      themselves as `Compiled 1 file(s) successfully` and a file `php -l`
      rejects. `SyntaxCheck` now reads the compiled PHP with PHP's own parser
      before anything is written, so they are reported with a line number and
      the last good output is left where it is.
- [x] **Functions of the same name** in one file no longer share a
      `Signatures` entry. A method is recorded under `Class::method`, which is
      also how PHP names one in a `TypeError`, so the guard and the message
      agree. Two classes in one file that each have an `all()` keep their own
      type arguments.

## Settled, previously blocked on phunkie

- [x] **`None` reports arity 0, the empty list reports arity 1.**

      ```php
      ImmList()->getTypeArity();      // 1, vars ["Nothing"]
      None()->getTypeArity();         // 0, vars []
      ```

      `Option::getTypeArity()` is `$this->isEmpty() ? 0 : 1` and stays that way.
      The guard takes both as the same thing: a container reporting no arguments
      has committed to nothing, exactly as one reporting `Nothing` has. That is
      one branch in `typeArgumentsSatisfy`, not a rule anybody writing phunkie
      has to know about.

## Next to specify

- [ ] **Two type parameters** — `ImmMap<String, User>`, so arity > 1 is
      exercised before anything harder. phunkie#41 is fixed, so a map now reports
      `Map<String, User>` and the guard says the same. As with `ImmList<Int>`
      reading back as `List<Int>`, the signature names the class and the message
      names the type, which is the constructor staying PHP's business.

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
