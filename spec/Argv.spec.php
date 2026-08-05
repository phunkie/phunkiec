<?php

use Phunkie\Compiler\Console\Argv;

describe("Argv", function () {
    it("splits a value written onto a short option", function () {
        expect((new Argv(["phunkiec", "src", "-o=build"]))->normalised())->toBe(["phunkiec", "src", "-o", "build"]);
    });

    it("splits every short option that carries one", function () {
        expect((new Argv(["phunkiec", "-m=macros", "-f=extra.syn"]))->normalised())
            ->toBe(["phunkiec", "-m", "macros", "-f", "extra.syn"]);
    });

    it("leaves a long option alone, because Symfony already reads it", function () {
        expect((new Argv(["phunkiec", "--out=build"]))->normalised())->toBe(["phunkiec", "--out=build"]);
    });

    it("leaves the forms Symfony already reads alone", function () {
        expect((new Argv(["phunkiec", "-o", "build", "-mbuild"]))->normalised())
            ->toBe(["phunkiec", "-o", "build", "-mbuild"]);
    });

    it("leaves an argument that merely looks like an option alone", function () {
        expect((new Argv(["phunkiec", "--", "-o=build"]))->normalised())->toBe(["phunkiec", "--", "-o=build"]);
    });
});
