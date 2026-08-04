<?php

given("there is a file {string} that is empty", function (string $path) {
    $file = $this->workspace . '/' . $path;

    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0777, true);
    }

    touch($file);
});

then("the compiler should have failed saying {string}", function (string $message) {
    expect($this->exitCode)->not()->toBe(0);
    expect($this->output)->toContain($message);
});

when("I compile {string} into {string}", function (string $input, string $output) {
    ($this->compile)($input, $output);
});

when("I ask for help", function () {
    ($this->run)('--help');
});

when("I run it with no arguments", function () {
    ($this->run)();
});

then("the output should mention {string}", function (string $text) {
    expect($this->output)->toContain($text);
});

then("the output should not mention {string}", function (string $text) {
    expect($this->output)->not()->toContain($text);
});

then("the file {string} should be created", function (string $path) {
    expect($this->exitCode)->toBe(0);
    expect(file_exists($this->workspace . '/' . $path))->toBeTrue();
});

then("the file {string} should be empty", function (string $path) {
    expect(file_get_contents($this->workspace . '/' . $path))->toBeEmpty();
});

// Writing PHP that is known not to parse hands back a build that looks whole
// and is not. The last good output is left where it is instead.
then("the file {string} should not have been created", function (string $path) {
    expect(file_exists($this->workspace . '/' . $path))->toBeFalse();
});

then("the failure should name the line it broke on", function () {
    expect($this->output)->toMatch('/on line \d+/');
});
