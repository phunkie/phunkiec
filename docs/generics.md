# Generics

> **Status: design.** Nothing in this document is implemented yet. It is written
> first, and each section becomes a feature file before it becomes code.

PHP has no generics. Phunkie's types are generic in every way that matters —
`ImmList` of what, `Option` of what — but the language gives you nowhere to say
so, and nowhere to check it.

phunkiec adds the notation, in angle brackets:

```php
function doubleAll(ImmList<Int> $numbers): ImmList<Int> {
    return $numbers->map(fn($n) => $n * 2);
}
```

Type arguments are erased at compile time, exactly as for comprehensions are
desugared: the PHP that comes out is the PHP you would have written by hand, plus
a check where one is needed.

## What it compiles to

```php
function doubleAll(ImmList $numbers): ImmList
{
    assertTypeArguments($numbers, 'List<Int>', 'doubleAll', 1, 'numbers');

    return $numbers->map(fn($n) => $n * 2);
}
```

The native `ImmList` type declaration is left to PHP, which already enforces it.
The part PHP cannot express — that it is a list *of integers* — becomes a guard
at the top of the body.

## The error

```php
doubleAll(ImmList("a", "b"));
```

```
TypeError: doubleAll(): Argument #1 ($numbers) must be of type List<Int>,
List<String> given
```

The wording deliberately matches PHP's own `TypeError`, because as far as the
caller is concerned nothing unusual has happened: they passed the wrong type and
were told so.

## Why this can be checked at all

Because Phunkie already knows. The type argument is not something phunkiec
invents and then has to track — it is recoverable from the value at runtime:

```php
ImmList(1, 2, 3)->showType();      // "List<Int>"
ImmList("a", "b")->showType();     // "List<String>"
ImmList(1, "a")->showType();       // "List<Mixed>"
ImmList()->showType();             // "List<Nothing>"
ImmList(Some(1))->showType();      // "List<Option<Int>>"
```

So the guard is a comparison, not an inference. This is why `ImmList` is where
generics start: the type argument is present in the value, and nesting already
works.

## Empty and mixed lists

Two of those answers are not ordinary types, and they decide what the guard
accepts.

`List<Nothing>` is the empty list. `Nothing` is the bottom type — below every
other type — so an empty list is a `List<Int>`, and a `List<String>`, and a list
of anything else. It has committed to nothing, so it satisfies everything:

```php
doubleAll(ImmList());     // fine: List<Nothing> is a List<Int>
```

`List<Mixed>` is a list whose elements are not all of one type. `Mixed` is the
top type — above every other type — so a `List<Mixed>` is *not* a `List<Int>`,
and passing one is an error:

```php
doubleAll(ImmList(1, "a"));
```

```
TypeError: doubleAll(): Argument #1 ($numbers) must be of type List<Int>,
List<Mixed> given
```

The two read as opposites because they are: `Nothing` accepts because it promises
nothing, `Mixed` refuses because it promises too little.
