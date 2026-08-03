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

    return assertReturnTypeArguments($numbers->map(fn($n) => $n * 2), 'List<Int>', 'doubleAll');
}
```

The native `ImmList` type declaration is left to PHP, which already enforces it.
The part PHP cannot express — that it is a list *of integers* — becomes a guard.
The signature made two promises, so there are two guards: one for what comes in,
one for what goes out.

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

## Return types

A return type argument is the more useful of the two, because it catches the
mistake at the point it was made rather than one call later. `map` will happily
change the element type, and PHP's own `: ImmList` cannot tell:

```php
function doubleAll(ImmList<Int> $numbers): ImmList<Int> {
    return $numbers->map(fn($n) => "doubled: " . $n);
}
```

```
TypeError: doubleAll(): Return value must be of type List<Int>, List<String>
returned
```

Again the wording is PHP's own, which uses `returned` rather than `given` for
this position.

Every `return` in the body is guarded, not just the last one, since any of them
can be the one that lies:

```php
function firstTwo(ImmList<Int> $numbers): ImmList<Int> {
    if ($numbers->isEmpty()) {
        return ImmList();
    }

    return $numbers->take(2);
}
```

```php
function firstTwo(ImmList $numbers): ImmList
{
    assertTypeArguments($numbers, 'List<Int>', 'firstTwo', 1, 'numbers');

    if ($numbers->isEmpty()) {
        return assertReturnTypeArguments(ImmList(), 'List<Int>', 'firstTwo');
    }

    return assertReturnTypeArguments($numbers->take(2), 'List<Int>', 'firstTwo');
}
```

That first return is the `Nothing` rule doing its job: `ImmList()` is
`List<Nothing>`, which satisfies `List<Int>`, so the guard passes and the empty
case needs no special handling.

A function with no return type argument gets no return guard, the same way a
parameter without one gets no parameter guard.

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
