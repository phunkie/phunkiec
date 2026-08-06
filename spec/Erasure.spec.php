<?php

use Phunkie\Compiler\Core\Grammar;
use Phunkie\Compiler\Generics\Erasure;

/**
 * What the compiler must never do to PHP that was already PHP.
 *
 * The grammar used to be asked only whether a source read, and a name it
 * mistook for a type cost nothing: erasure had its own scanner and ignored the
 * answer. Erasure now removes exactly the stretches the grammar names, so the
 * same mistake deletes code from a file that was already correct.
 *
 * Both halves are asked, and asking only one is worse than useless. `erase()`
 * hands a source straight back the moment the grammar reports anything it
 * could not read, so a file the compiler refuses outright is a file erasure
 * leaves perfect. Left to itself, this suite would be satisfied most cheaply
 * by exactly the files that do not compile.
 */
describe("Erasure", function () {
    $corpus = function (): array {
        $found = [];

        $roots = [
            "src",
            "spec",
            "vendor/nikic/php-parser/lib",
            "vendor/symfony/console",
            "vendor/symfony/finder",
        ];

        foreach ($roots as $root) {
            $directory = dirname(__DIR__) . "/" . $root;

            if (!is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

            foreach ($files as $file) {
                if ($file->getExtension() === "php") {
                    $found[] = $file->getPathname();
                }
            }
        }

        return $found;
    };

    it("leaves PHP that was already PHP exactly as it was", function () use ($corpus) {
        $erasure = new Erasure();
        $touched = [];

        foreach ($corpus() as $path) {
            $source = (string) file_get_contents($path);

            if ($erasure->erase($source) !== $source) {
                $touched[] = substr($path, strlen(dirname(__DIR__)) + 1);
            }
        }

        expect($touched)->toBe([]);
    });

    // A comparison against something qualified is what this is really about.
    // `$this->id < 256` and `self::LIMIT << 1` read as the opening of a type
    // argument list, because a qualified name is exactly what a type may be
    // called, and every one of these files is full of them.
    it("compiles PHP that was already PHP rather than refusing it", function () use ($corpus) {
        $grammar = new Grammar();
        $refused = [];

        foreach ($corpus() as $path) {
            try {
                $grammar->assertReads((string) file_get_contents($path));
            } catch (RuntimeException $error) {
                $refused[] = substr($path, strlen(dirname(__DIR__)) + 1) . ": " . $error->getMessage();
            }
        }

        expect($refused)->toBe([]);
    });

    it("has PHP to read in the first place", function () use ($corpus) {
        expect(count($corpus()))->toBeGreaterThan(400);
    });
});
