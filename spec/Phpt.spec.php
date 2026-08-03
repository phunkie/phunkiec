<?php

use Phunkie\Compiler\Tests\PhptRunner;

require_once __DIR__ . "/../tests/phpt/support/PhptRunner.php";

describe("phpt fixtures", function () {
    foreach (PhptRunner::fixtures(__DIR__ . "/../tests/phpt") as $group => $files) {
        describe($group, function () use ($files) {
            foreach ($files as $phpt) {
                $sections = PhptRunner::parse((string) file_get_contents($phpt));
                $name = $sections["TEST"] ?? basename($phpt, ".phpt");

                // A fixture may describe a feature that is not built yet. It is
                // still reported by name, so the intent stays visible, and it
                // asserts the feature is *still* missing: the day it lands, this
                // fails and the marker has to be removed rather than rotting.
                if (isset($sections["PENDING"])) {
                    it($name . " (pending)", function () use ($sections) {
                        $expected = $sections["EXPECT"];

                        expect(PhptRunner::compileOrFailure($sections))
                            ->toSatisfy(fn ($compiled) => $compiled !== $expected);
                    });

                    continue;
                }

                it($name, function () use ($sections) {
                    expect(PhptRunner::compile($sections))->toBe($sections["EXPECT"]);
                });
            }
        });
    }
});
