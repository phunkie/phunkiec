<?php

use Phunkie\Compiler\Core\MacroDiscovery;

describe("MacroDiscovery", function () {
    it("finds the macro directory a package declares via extra.phunkiec.macros", function () {
        $root = __DIR__ . "/fixtures/package-with-macros";

        expect((new MacroDiscovery([$root]))->discover())->toContain($root . "/macros");
    });

    it("ignores a package that does not opt in", function () {
        $root = __DIR__ . "/fixtures/package-without-macros";

        expect((new MacroDiscovery([$root]))->discover())->toBe([]);
    });

    it("ignores a declared directory that does not exist", function () {
        $root = __DIR__ . "/fixtures/package-missing-dir";

        expect((new MacroDiscovery([$root]))->discover())->toBe([]);
    });

    it("ignores a root that has no composer.json", function () {
        expect((new MacroDiscovery([__DIR__ . "/fixtures/nope"]))->discover())->toBe([]);
    });
});
