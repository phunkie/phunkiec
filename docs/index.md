# phunkiec

phunkiec compiles `.phunkie` files into standard PHP. The two language features it
adds — **for comprehensions** and **pattern matching** — are desugared at compile
time onto ordinary method calls and PHP's native `match`, so there is no runtime
cost and no library magic at the call site.

```bash
phunkiec src --out build
```

See the [README](../README.md) for the full CLI and how a package ships its own
macros.

## Guides

- [For comprehensions](for-comprehension.md) — `for { … } yield …` over any
  monadic type.
- [Pattern matching](pattern-matching.md) — `value match { … }` with
  deconstruction and guards.

## How it works

Each feature is a set of macros (`.syn` files) applied to the token stream:

- A **for comprehension** becomes a chain of `flatMap` / `map` (with `yield`),
  `withEach` (statement body) and `withFilter` (guards). Any type carrying those
  methods works — the desugaring names no type.
- A **pattern match** becomes `(fn($on) => match (true) { … })(pmatch($subject))`,
  where each case asks phunkie's `pmatch` whether it matches. Constructor patterns
  such as `Some($x)` are rewritten to phunkie's referenced patterns so they bind.

Because it is all compile-time rewriting, the output is plain PHP you can read,
lint and run without phunkiec present.
