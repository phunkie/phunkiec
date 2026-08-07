<?php

/**
 * Compares PHP for meaning rather than layout: whitespace and the opening tag
 * are irrelevant to whether the comprehension desugared correctly.
 */
$asTokens = function (string $php): string {
    $significant = [];

    foreach (token_get_all($php) as $token) {
        if (!is_array($token)) {
            $significant[] = $token;
            continue;
        }

        if ($token[0] === T_WHITESPACE || $token[0] === T_OPEN_TAG) {
            continue;
        }

        $significant[] = $token[1];
    }

    return implode(' ', $significant);
};

given("a phunkie file containing:", function (string $source) {
    $file = $this->workspace . '/src/Example.phunkie';
    mkdir(dirname($file), 0777, true);
    file_put_contents($file, "<?php\n\n" . $source . "\n");
});

// Written exactly as given, tag and all missing, because opening the tag is
// what moves every line down and is the thing under test.
given("a phunkie file with no opening tag containing:", function (string $source) {
    $file = $this->workspace . '/src/Example.phunkie';

    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0777, true);
    }

    file_put_contents($file, $source . "\n");
});

when("I compile it", function () {
    ($this->compile)('src/Example.phunkie', 'build/Example.php');
});

then("the compiled PHP should be equivalent to:", function (string $expected) use ($asTokens) {
    expect($this->exitCode)->toBe(0);

    $compiled = file_get_contents($this->workspace . '/build/Example.php');

    expect($asTokens($compiled))->toBe($asTokens("<?php\n\n" . $expected));
});
