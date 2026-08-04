# phunkiec

The Phunkie preprocessor. It compiles `.phunkie` files into standard PHP: for
comprehensions and pattern matching are desugared at compile time, so there is no
runtime cost.

## Usage

```bash
phunkiec <input> --out <path> [--watch] [--macro-dir <dir>]... [--macro-file <file>]...
```

- `<input>` — a `.phunkie` file or a directory of them.
- `--out` / `-o` — output file or directory (required).
- `--watch` / `-w` — keep running, recompiling a source as it is saved.
- `--macro-dir` / `-m` — an extra directory of `.syn` macros (repeatable).
- `--macro-file` / `-f` — an extra `.syn` macro file (repeatable).

A short option takes its value either way round, `-o build` or `-o=build`.

```bash
phunkiec src --out build
```

## Watching

```bash
phunkiec --watch -o=build src
```

Everything under `src` is compiled once, and then the directory is watched.
Saving a source recompiles that source alone, into the same place under `build`
that it holds under `src`, and reports it:

```
Compiled 3 file(s) successfully.
Watching src. Press Ctrl+C to stop.
OK src/App/Todo.phunkie (54 lines) → build/App/Todo.php
```

A new source is picked up without restarting. A source that fails to compile is
reported and the watch carries on, so a half-written save costs nothing but the
next save.

Changes are noticed by polling four times a second, comparing the contents of
each source rather than its timestamp: `filemtime` has one-second resolution, so
two saves inside the same second are indistinguishable by time and the second
would be missed.

Removing a source leaves the PHP it produced behind. Deleting generated files on
the strength of a poll is a worse failure than leaving one to be cleaned up.

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
