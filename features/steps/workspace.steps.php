<?php

$phunkiec = dirname(__DIR__, 2) . '/bin/phunkiec';

beforeScenario(function () use ($phunkiec) {
    $this->workspace = sys_get_temp_dir() . '/phunkiec-features-' . uniqid();
    mkdir($this->workspace, 0777, true);

    $this->compile = function (string $input, string $output) use ($phunkiec) {
        $command = sprintf(
            'cd %s && %s %s %s --out %s 2>&1',
            escapeshellarg($this->workspace),
            escapeshellarg(PHP_BINARY),
            escapeshellarg($phunkiec),
            escapeshellarg($input),
            escapeshellarg($output)
        );

        exec($command, $lines, $exitCode);

        $this->output = implode("\n", $lines);
        $this->exitCode = $exitCode;
    };
});

afterScenario(function () {
    exec('rm -rf ' . escapeshellarg($this->workspace));
});
