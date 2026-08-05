<?php

use Phunkie\Compiler\Core\OutputDirectory;

describe("OutputDirectory", function () {
    it("puts a source at the same relative path, as PHP", function () {
        expect((new OutputDirectory("build"))->forSource("App/Todo.phunkie"))->toBe("build/App/Todo.php");
    });

    it("keeps a source that sits at the root at the root", function () {
        expect((new OutputDirectory("build"))->forSource("Todo.phunkie"))->toBe("build/Todo.php");
    });

    it("renames the extension and nothing else that looks like one", function () {
        expect((new OutputDirectory("build"))->forSource("phunkie.sources/a.phunkie"))->toBe("build/phunkie.sources/a.php");
    });
});
