<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Tests;

use Phunkie\Compiler\Core\PhunkieProcessor;
use Syn\Core\Configuration;

/**
 * Runs a phunkiec `.phpt` fixture: compiles its --FILE-- section, honouring an
 * optional --MACRO-FILE-- section, and returns the generated PHP so a spec can
 * compare it against --EXPECT--.
 *
 * The format mirrors syn's phpt tests. Recognised sections:
 *   --TEST--       one-line description (used as the example name)
 *   --MACRO-FILE-- inline `.syn` macros, added ahead of the bundled ones so a
 *                  rule here overrides a bundled rule of the same shape
 *   --FILE--       the `.phunkie` source to compile
 *   --EXPECT--     the expected compiled PHP (compared trimmed)
 *   --PENDING--    why this describes a feature that does not exist yet
 */
final class PhptRunner
{
    /**
     * Finds the fixtures, grouped by the directory they sit in, so the suite
     * reads by feature rather than as one long list.
     *
     * @return array<string, list<string>>
     */
    public static function fixtures(string $root): array
    {
        $groups = [];

        foreach ((array) glob($root . '/*', GLOB_ONLYDIR) as $directory) {
            $name = basename((string) $directory);

            if ($name === 'support') {
                continue;
            }

            $files = (array) glob($directory . '/*.phpt');
            sort($files);

            if ($files !== []) {
                $groups[$name] = $files;
            }
        }

        ksort($groups);

        return $groups;
    }

    /**
     * Compiles, treating a compile failure as a result rather than an error.
     *
     * A pending fixture describes syntax the compiler cannot parse yet, so
     * failing to compile is the expected outcome and must not abort the run.
     *
     * @param array<string, string> $sections
     */
    public static function compileOrFailure(array $sections): string
    {
        try {
            return self::compile($sections);
        } catch (\Throwable $e) {
            return 'compile failed: ' . $e->getMessage();
        }
    }
    /**
     * @return array<string, string> section name (without dashes) => body
     */
    public static function parse(string $content): array
    {
        $sections = [];
        $current = null;

        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^--([A-Z_-]+)--\s*$/', $line, $matches) === 1) {
                $current = $matches[1];
                $sections[$current] = '';

                continue;
            }

            if ($current !== null) {
                $sections[$current] .= $line . "\n";
            }
        }

        return array_map(static fn (string $body): string => trim($body), $sections);
    }

    /**
     * @param array<string, string> $sections
     */
    public static function compile(array $sections): string
    {
        $configuration = new Configuration();
        $temporary = [];

        if (($sections['MACRO-FILE'] ?? '') !== '') {
            $macroFile = self::temporaryFile('macro', 'syn', $temporary);
            file_put_contents($macroFile, $sections['MACRO-FILE']);
            $configuration->addMacroFile($macroFile);
        }

        $input = self::temporaryFile('in', 'phunkie', $temporary);
        $output = self::temporaryFile('out', 'php', $temporary);
        file_put_contents($input, ($sections['FILE'] ?? '') . "\n");

        $results = (new PhunkieProcessor($configuration))->process($input, $output);

        // Nothing is written for a source that compiles to PHP which does not
        // parse, so the output is read only if it is there, and the reason is
        // raised rather than the fixture reporting an empty file.
        $compiled = is_file($output) ? (string) file_get_contents($output) : '';

        foreach ($temporary as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        foreach ($results as $result) {
            if ($result['status'] === 'error') {
                throw new \RuntimeException($result['error']);
            }
        }

        return trim($compiled);
    }

    /**
     * tempnam creates the file it names, and the extension has to go on the
     * end of that name, so both paths are remembered and both are removed.
     *
     * @param list<string> $temporary
     */
    private static function temporaryFile(string $purpose, string $extension, array &$temporary): string
    {
        $base = (string) tempnam(sys_get_temp_dir(), 'phunkiec_' . $purpose . '_');
        $path = $base . '.' . $extension;

        $temporary[] = $base;
        $temporary[] = $path;

        return $path;
    }
}
