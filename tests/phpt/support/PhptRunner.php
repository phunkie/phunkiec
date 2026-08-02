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
 */
final class PhptRunner
{
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

        if (($sections['MACRO-FILE'] ?? '') !== '') {
            $macroFile = tempnam(sys_get_temp_dir(), 'phunkiec_macro_') . '.syn';
            file_put_contents($macroFile, $sections['MACRO-FILE']);
            $configuration->addMacroFile($macroFile);
        }

        $input = tempnam(sys_get_temp_dir(), 'phunkiec_in_') . '.phunkie';
        $output = tempnam(sys_get_temp_dir(), 'phunkiec_out_') . '.php';
        file_put_contents($input, ($sections['FILE'] ?? '') . "\n");

        (new PhunkieProcessor($configuration))->process($input, $output);

        $compiled = (string) file_get_contents($output);

        @unlink($input);
        @unlink($output);

        return trim($compiled);
    }
}
