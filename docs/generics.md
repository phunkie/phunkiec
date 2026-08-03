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
    assertTypeArguments($numbers, ['Int'], 'doubleAll', 1, 'numbers');

    return assertReturnTypeArguments($numbers->map(fn($n) => $n * 2), ['Int'], 'doubleAll');
}
```

The native `ImmList` type declaration is left to PHP, which already enforces it.
The part PHP cannot express — that it is a list *of integers* — becomes a guard.
The signature made two promises, so there are two guards: one for what comes in,
one for what goes out.

Note what the guard is given: the type *arguments*, not the whole type. The
constructor is PHP's business, the arguments are ours, and keeping them apart is
what makes a subtype work. `NonEmptyList` extends `ImmList`, so

```php
function head(NonEmptyList $xs): Int
```

is already enforced by PHP, and the guard beside it only has to agree about
`Int`. Had the guard compared rendered type names instead, it would have been
looking for `NonEmptyList<Int>` against a value that reports itself as
`List<Int>`, and never matched.

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
    assertTypeArguments($numbers, ['Int'], 'firstTwo', 1, 'numbers');

    if ($numbers->isEmpty()) {
        return assertReturnTypeArguments(ImmList(), ['Int'], 'firstTwo');
    }

    return assertReturnTypeArguments($numbers->take(2), ['Int'], 'firstTwo');
}
```

That first return is the `Nothing` rule doing its job: `ImmList()` has `Nothing`
for its type argument, which satisfies `Int`, so the guard passes and the empty
case needs no special handling.

A function with no return type argument gets no return guard, the same way a
parameter without one gets no parameter guard.

## Why this can be checked at all

Because Phunkie already knows. The type arguments are not something phunkiec
invents and then has to track — every `Kind` reports its own, at runtime:

```php
ImmList(1, 2, 3)->getTypeVariables();   // ["Int"]
ImmList("a", "b")->getTypeVariables();  // ["String"]
ImmList(1, "a")->getTypeVariables();    // ["Mixed"]
ImmList()->getTypeVariables();          // ["Nothing"]
Nel(1, 2, 3)->getTypeVariables();       // ["Int"]
ImmMap(["a" => 1])->getTypeVariables(); // ["String", "Int"]
```

So the guard is a comparison, not an inference. This is why `ImmList` is where
generics start: the arguments are present in the value, arity greater than one
falls out for free, and a subtype reports the same arguments as its parent.

`showType()` renders the same information for a human, and is what the error
messages use:

```php
ImmList(1, 2, 3)->showType();      // "List<Int>"
ImmList(Some(1))->showType();      // "List<Option<Int>>"
```

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

## Option, and the absent value

`Option<T>` is the case where the type argument matters most and the value knows
least:

```php
function getAddress(Option<User> $user): Option<Address> {
    return $user->map(fn($u) => $u->address());
}
```

`Some` is straightforward. It holds a value, so it can say what it holds:

```php
Some($user)->getTypeVariables();   // ["User"]
```

`None` cannot. There is exactly one `None` object in a program, shared by every
absence in it, so it has nothing to inspect and nowhere to record an answer:

```php
$a = None();
$b = Some(42)->filter(fn($n) => $n > 100);       // Option<Int>  -> None
$c = Some("hello")->filter(fn($s) => false);     // Option<String> -> None

$a === $b;   // true
$b === $c;   // true, the same object
```

Tagging that object with `Int` because it came out of an `Option<Int>` would tag
it for the whole program, including the absence that came from an
`Option<String>`. So the type of an absent value is never carried by the value.

This is why the type is static. `getAddress(None())` is a call the runtime could
never check on its own, and it does not have to: the signature says `Option<User>`
and that is where the argument comes from.

For the guard, `None` behaves exactly as the empty list does. It has committed to
nothing, so it satisfies every `Option<A>`:

```php
getAddress(None());       // fine, for the same reason ImmList() is a List<Int>
getAddress(Some($user));  // fine
getAddress(Some($order)); // TypeError: Option<Order> given
```

No new rule: the one that accepts an empty list accepts an absent value, because
both are empty containers that named no argument.

