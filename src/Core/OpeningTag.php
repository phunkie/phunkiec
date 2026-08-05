<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Core;

/**
 * Opens a source that did not open itself.
 *
 * A .phunkie file is only ever PHP, so saying `<?php` on the first line of
 * every one of them says nothing. The compiler supplies it, and the file starts
 * with the code.
 *
 * This runs before anything reads the source, because everything that follows
 * works on tokens: without an opening tag PHP lexes the whole file as inline
 * HTML, and no macro, no type argument and no guard would be seen at all.
 *
 * A source that opens its own tag is left exactly as it is, in any of the forms
 * PHP accepts, so nothing already written has to change. A source with nothing
 * in it stays that way: an empty file compiles to an empty file.
 */
final class OpeningTag
{
    private const TAG = "<?php\n\n";

    public function ensure(string $source): string
    {
        if (trim($source) === '') {
            return $source;
        }

        if (preg_match('/^\s*<\?/', $source) === 1) {
            return $source;
        }

        return self::TAG . $source;
    }
}
