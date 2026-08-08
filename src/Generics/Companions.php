<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

use Phunkie\Stan\Type\Notation;

/**
 * Adds the companion functions a source's attributes asked for.
 *
 * Where they go is decided by what the file is. A namespaced file is a
 * declaration file: its namespace is bracketed and the functions follow in a
 * global block, at the end, where nothing runs before they load. A file with
 * no namespace is a script whose statements run where they stand, so the
 * block goes just under the tag, ahead of the first of them.
 */
final class Companions
{
    public function __construct(
        private readonly Notation $notation = new Notation(),
        private readonly CompanionWriter $writer = new CompanionWriter(),
    ) {
    }

    /**
     * @param string $source   Source with its tag, notation intact
     * @param string $compiled The pipeline's output for it
     *
     * @return string The output with its companions, untouched when there are none
     */
    public function addTo(string $source, string $compiled): string
    {
        $companions = $this->notation->readFrom($source)->companions;

        if ($companions === []) {
            return $compiled;
        }

        $namespace = null;

        if (preg_match('/^namespace\s+([A-Za-z0-9_\\\\]+)\s*;/m', $compiled, $found, PREG_OFFSET_CAPTURE) === 1) {
            $namespace = $found[1][0];
        }

        $block = $this->writer->block($companions, $namespace);

        if ($namespace === null) {
            return (string) preg_replace('/^<\?php\s*?\n/', "<?php\n\n" . $block . "\n", $compiled, 1);
        }

        [$statement, $at] = $found[0];

        return substr($compiled, 0, $at)
            . 'namespace ' . $namespace . " {\n"
            . substr($compiled, $at + strlen($statement))
            . "\n}\n\nnamespace {\n\n" . $block . "\n\n}\n";
    }
}
