--TEST--
Ignoring generated values with the wildcard
--FILE--
for {
    $line <- IO\readline()
    _ <- IO\write($line, '/tmp/some_file.txt')
    _ <- IO\printLn("You have successfully written to file")
} yield ();
--EXPECT--
<?php IO\readline()->flatMap(function ($line) { return IO\write($line, '/tmp/some_file.txt')->flatMap(function ($_) use ($line) { return IO\printLn("You have successfully written to file")->map(function ($_) use ($line) { return Unit(); }); }); });
