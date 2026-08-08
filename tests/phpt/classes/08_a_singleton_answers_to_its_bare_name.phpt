--TEST--
A singleton companion is one instance, with or without its parentheses
--FILE--
#[Companion(singleton: true, withArguments: false)]
final class Anchor;

echo Anchor() === Anchor() ? "one instance\n" : "two instances\n";
echo Anchor === Anchor() ? "the bare name is it\n" : "the bare name is another\n";
--RUN--
one instance
the bare name is it
