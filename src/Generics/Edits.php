<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

/**
 * Every change to a source, applied at once.
 *
 * Applied from the end backwards, so an offset read from the source before any
 * of this ran is still the offset it names when its turn comes. That is what
 * lets the notation be found by one reader and taken out by another without
 * either having to know what the other moved.
 */
final class Edits
{
    /**
     * @param list<Edit> $edits Changes, in any order
     */
    public function __construct(private readonly array $edits)
    {
    }

    /**
     * @param string $source Source the offsets were read from
     *
     * @return string The source with every change made
     */
    public function applyTo(string $source): string
    {
        foreach (array_reverse($this->ordered()) as $edit) {
            $width = $this->widthOf($edit, $source);
            $source = substr_replace($source, $edit->text, $edit->region->from, $width);
        }

        return $source;
    }

    /**
     * In source order, with removals that touch run together.
     *
     * A callable return type is taken out as its colon and then as itself, and
     * the two are neighbours. Left apart, the second would decide about the
     * space after it by looking at the first, which is about to go, and answer
     * for a source that will not exist.
     *
     * @return list<Edit>
     */
    private function ordered(): array
    {
        $edits = $this->edits;

        usort($edits, static fn (Edit $one, Edit $other): int => $one->region->from <=> $other->region->from);

        $ordered = [];

        foreach ($edits as $edit) {
            $last = array_key_last($ordered);

            if ($last !== null && $ordered[$last]->touches($edit)) {
                $ordered[$last] = $ordered[$last]->mergedWith($edit);

                continue;
            }

            $ordered[] = $edit;
        }

        return $ordered;
    }

    /**
     * How much of the source a change takes with it.
     *
     * A removal takes the space that followed the notation where there is
     * nothing in front for that space to have been separating, so `(T $item)`
     * becomes `($item)` rather than `( $item)`. Where a name is in front the
     * space is doing its job and stays, and `ImmList<Int> $xs` becomes
     * `ImmList $xs`.
     */
    private function widthOf(Edit $edit, string $source): int
    {
        $width = $edit->region->to - $edit->region->from;

        if (!$edit->removes() || !$this->nothingInFrontOf($source, $edit->region->from)) {
            return $width;
        }

        return $width + strspn($source, ' ', $edit->region->to);
    }

    private function nothingInFrontOf(string $source, int $at): bool
    {
        return $at === 0 || str_contains("(,\t\n ", $source[$at - 1]);
    }
}
