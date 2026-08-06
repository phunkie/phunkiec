<?php

use Phunkie\Compiler\Core\Grammar;

/**
 * The contract between the two readers.
 *
 * phunkiec erases the notation and phunkistan reads it, and they are separate
 * pieces of code looking at the same text. Nothing but this makes them agree.
 * If the grammar refuses a fixture the compiler handles, the compiler is
 * accepting a language the checker does not have, which is the drift the
 * grammar exists to end, arriving inside one project rather than between two.
 */
describe("Grammar", function () {
    $fixtures = function (): array {
        $found = [];

        foreach (glob(dirname(__DIR__) . "/tests/phpt/*/*.phpt") ?: [] as $path) {
            $source = file_get_contents($path);

            if ($source === false || !preg_match('/--FILE--\n(.*?)\n--EXPECT/s', $source, $matched)) {
                continue;
            }

            $found[substr($path, strlen(dirname(__DIR__)) + 1)] = $matched[1];
        }

        return $found;
    };

    it("reads every source the compiler is expected to compile", function () use ($fixtures) {
        $refused = [];

        foreach ($fixtures() as $name => $source) {
            try {
                (new Grammar())->assertReads($source);
            } catch (RuntimeException $error) {
                $refused[] = $name . ": " . $error->getMessage();
            }
        }

        expect($refused)->toBe([]);
    });

    it("has fixtures to read in the first place", function () use ($fixtures) {
        expect(count($fixtures()))->toBeGreaterThan(20);
    });

    // The three the compiler's own suite caught, kept here so a grammar change
    // is measured against the language rather than against its own examples.
    it("leaves alone the notation phunkie writes that only looks like a type", function () {
        $written = [
            'an arrow function' => '$xs->map(fn($n) => $n * 2);',
            'a constructor pattern' => '$r = $x match { Some($v) => $v };',
            'a tuple pattern' => '$r = $x match { ($a, $b) => $a };',
        ];

        foreach ($written as $source) {
            expect(fn () => (new Grammar())->assertReads($source))->not()->toThrow(RuntimeException::class);
        }
    });
});
