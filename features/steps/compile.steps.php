<?php

given("there is a file {string} that is empty", function (string $path) {
    $file = $this->workspace . '/' . $path;
    mkdir(dirname($file), 0777, true);
    touch($file);
});

when("I compile {string} into {string}", function (string $input, string $output) {
    ($this->compile)($input, $output);
});

then("the file {string} should be created", function (string $path) {
    expect($this->exitCode)->toBe(0);
    expect(file_exists($this->workspace . '/' . $path))->toBeTrue();
});

then("the file {string} should be empty", function (string $path) {
    expect(file_get_contents($this->workspace . '/' . $path))->toBeEmpty();
});
