<?php

use Phunkie\Compiler\Core\SourceTree;

describe("SourceTree", function () {
    $inWorkspace = function (Closure $body): void {
        $directory = sys_get_temp_dir() . "/phunkiec-source-tree-" . uniqid();
        mkdir($directory . "/App", 0777, true);

        try {
            $body($directory);
        } finally {
            exec(sprintf("rm -rf %s", escapeshellarg($directory)));
        }
    };

    $relativePaths = function (SourceTree $tree): array {
        $paths = array_map(fn ($source) => $source->relativePath, $tree->files());
        sort($paths);

        return $paths;
    };

    it("finds every source, however deep, and nothing else", function () use ($inWorkspace, $relativePaths) {
        $inWorkspace(function (string $directory) use ($relativePaths) {
            file_put_contents($directory . "/Root.phunkie", "<?php\n");
            file_put_contents($directory . "/App/Todo.phunkie", "<?php\n");
            file_put_contents($directory . "/App/Todo.php", "<?php\n");
            file_put_contents($directory . "/App/notes.md", "hello");

            expect($relativePaths(new SourceTree($directory)))->toBe(["App/Todo.phunkie", "Root.phunkie"]);
        });
    });

    it("finds nothing in a directory with no sources", function () use ($inWorkspace, $relativePaths) {
        $inWorkspace(function (string $directory) use ($relativePaths) {
            expect($relativePaths(new SourceTree($directory)))->toBe([]);
        });
    });

    // The fingerprint is taken from the contents rather than the modification
    // time, because filemtime has one-second resolution: two saves inside the
    // same second are indistinguishable by time, and the second one would be
    // silently missed.
    it("fingerprints a source by its contents", function () use ($inWorkspace) {
        $inWorkspace(function (string $directory) {
            file_put_contents($directory . "/App/Todo.phunkie", "<?php\n");
            $before = (new SourceTree($directory))->files()[0]->fingerprint();

            file_put_contents($directory . "/App/Todo.phunkie", "<?php // changed\n");
            $after = (new SourceTree($directory))->files()[0]->fingerprint();

            expect($before)->not()->toBe($after);
        });
    });

    it("gives a source the absolute path it can be read from", function () use ($inWorkspace) {
        $inWorkspace(function (string $directory) {
            file_put_contents($directory . "/App/Todo.phunkie", "<?php // here\n");

            expect(file_get_contents((new SourceTree($directory))->files()[0]->path))->toBe("<?php // here\n");
        });
    });
});
