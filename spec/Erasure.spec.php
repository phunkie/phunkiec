<?php

use Phunkie\Compiler\Generics\Erasure;

/**
 * What erasure must never do.
 *
 * The grammar used to be asked only whether a source read, and a name it
 * mistook for a type cost nothing: erasure had its own scanner and ignored the
 * answer. Erasure now removes exactly the stretches the grammar names, so the
 * same mistake deletes code from a file that was already correct, and deletes
 * it quietly.
 *
 * So the grammar is pointed at a large body of PHP nobody wrote for it. Every
 * one of these files is already PHP, none of them is phunkie, and erasure has
 * nothing to do to any of them.
 */
describe("Erasure", function () {
    $corpus = function (): array {
        $found = [];

        foreach (["src", "spec", "vendor/nikic/php-parser/lib"] as $root) {
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

    it("has PHP to read in the first place", function () use ($corpus) {
        expect(count($corpus()))->toBeGreaterThan(100);
    });
});
