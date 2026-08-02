# phunkiec

The Phunkie preprocessor. It compiles `.phunkie` files into standard PHP: for
comprehensions and pattern matching are desugared at compile time, so there is no
runtime cost.

## Usage

```bash
phunkiec <input> --out <path> [--macro-dir <dir>]... [--macro-file <file>]...
```

- `<input>` — a `.phunkie` file or a directory of them.
- `--out` / `-o` — output file or directory (required).
- `--macro-dir` / `-m` — an extra directory of `.syn` macros (repeatable).
- `--macro-file` / `-f` — an extra `.syn` macro file (repeatable).

```bash
phunkiec src --out build
```

## Macros

phunkiec loads macros from three places, highest precedence first (the first
matching rule wins):

1. **Explicit** — anything passed with `--macro-file` / `--macro-dir`.
2. **Discovered** — macros shipped by installed composer packages (see below).
3. **Bundled** — the rules that ship with phunkiec, in its own `macros/`.

So a package's rule overrides a bundled one for the same surface syntax, and an
explicitly supplied macro overrides everything.

### Shipping macros from a package

A library can ship `.syn` macros for its own types and have phunkiec discover
them automatically. Declare a macros directory in the package's `composer.json`:

```json
{
    "extra": {
        "phunkiec": {
            "macros": "macros/"
        }
    }
}
```

Every `.syn` file in that directory is loaded whenever phunkiec compiles a project
that has the package installed. This is how `phunkie/effect` ships the pattern for
its own `IO` — matching an effect `IO` needs a rule pointing at effect's
`Referenced\IO`, which phunkiec itself does not know about.

Discovery uses composer's runtime metadata (`Composer\InstalledVersions`), so it
sees exactly the packages installed alongside the project being compiled.
