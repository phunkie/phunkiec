<?php

$phunkiec = dirname(__DIR__, 2) . '/bin/phunkiec';

/**
 * Waits for a condition instead of sleeping a fixed time. How long a watcher
 * takes to notice a save is not something a test can know, and guessing is what
 * makes this kind of test flaky.
 */
$eventually = function (Closure $condition, float $seconds = 10.0): bool {
    $deadline = microtime(true) + $seconds;

    do {
        if ($condition()) {
            return true;
        }

        usleep(50_000);
    } while (microtime(true) < $deadline);

    return false;
};

$write = function (string $workspace, string $path, string $source): void {
    $file = $workspace . '/' . $path;

    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0777, true);
    }

    file_put_contents($file, "<?php\n\n" . $source . "\n");
};

given("there is a source {string} containing {string}", function (string $path, string $source) use ($write) {
    $write($this->workspace, $path, $source);
});

given("the compiler is watching {string} into {string}", function (string $input, string $output) use ($phunkiec, $eventually) {
    $this->watchLog = $this->workspace . '/watch.log';

    // The array form of proc_open runs the binary directly, with no shell in
    // between, so terminating the process afterwards terminates the watcher
    // rather than a shell that outlives it.
    $this->watch = proc_open(
        [PHP_BINARY, $phunkiec, $input, '--out', $output, '--watch'],
        [1 => ['file', $this->watchLog, 'w'], 2 => ['file', $this->watchLog, 'a']],
        $pipes,
        $this->workspace
    );

    // The step promises the compiler is watching, so it waits until it says so.
    // Saving before the first snapshot is taken would be caught by the compile
    // that runs at startup instead, and the scenario would pass without the
    // watch having done anything.
    $watching = $eventually(
        fn () => is_file($this->watchLog) && str_contains((string) file_get_contents($this->watchLog), 'Watching')
    );

    expect($watching)->toBeTrue();
});

when("I save {string} containing {string}", function (string $path, string $source) use ($write) {
    $write($this->workspace, $path, $source);
});

when("I compile {string} with {string}", function (string $input, string $option) {
    ($this->run)($input, $option);
});

then("{string} should eventually contain {string}", function (string $path, string $needle) use ($eventually) {
    $file = $this->workspace . '/' . $path;

    $arrived = $eventually(fn () => is_file($file) && str_contains((string) file_get_contents($file), $needle));

    expect($arrived)->toBeTrue();
});

then("the watch log should eventually contain {string}", function (string $needle) use ($eventually) {
    $arrived = $eventually(
        fn () => is_file($this->watchLog) && str_contains((string) file_get_contents($this->watchLog), $needle)
    );

    expect($arrived)->toBeTrue();
});

// Registered before the hook that removes the workspace, so the watcher is gone
// before the directory it is polling is.
afterScenario(function () {
    if (!isset($this->watch) || !is_resource($this->watch)) {
        return;
    }

    proc_terminate($this->watch);
    proc_close($this->watch);
});
