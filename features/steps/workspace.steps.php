<?php

$phunkiec = dirname(__DIR__, 2) . '/bin/phunkiec';

beforeScenario(function () use ($phunkiec) {
    $this->workspace = sys_get_temp_dir() . '/phunkiec-features-' . uniqid();
    mkdir($this->workspace, 0777, true);

    $this->run = function (string ...$arguments) use ($phunkiec) {
        $command = sprintf(
            'cd %s && %s %s %s 2>&1',
            escapeshellarg($this->workspace),
            escapeshellarg(PHP_BINARY),
            escapeshellarg($phunkiec),
            implode(' ', array_map('escapeshellarg', $arguments))
        );

        exec($command, $lines, $exitCode);

        $this->output = implode("\n", $lines);
        $this->exitCode = $exitCode;
    };

    $this->compile = function (string $input, string $output) {
        ($this->run)($input, '--out', $output);
    };
});

afterScenario(function () {
    exec('rm -rf ' . escapeshellarg($this->workspace));
});
