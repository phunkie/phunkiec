--TEST--
The mirror a companion wrote constructs the class
--FILE--
#[Companion]
final class Coin(string $currency);

echo Coin("gbp")->currency, "\n";
--RUN--
gbp
