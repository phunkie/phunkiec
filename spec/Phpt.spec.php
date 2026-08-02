<?php

use Phunkie\Compiler\Tests\PhptRunner;

require_once __DIR__ . "/../tests/phpt/support/PhptRunner.php";

describe("phpt fixtures", function () {
    foreach (glob(__DIR__ . "/../tests/phpt/*.phpt") as $phpt) {
        $sections = PhptRunner::parse((string) file_get_contents($phpt));
        $name = $sections["TEST"] ?? basename($phpt, ".phpt");

        it($name, function () use ($sections) {
            expect(PhptRunner::compile($sections))->toBe($sections["EXPECT"]);
        });
    }
});
