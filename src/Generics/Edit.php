<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Generics;

use Phunkie\Stan\Source\Region;

/**
 * One change to a source, as a stretch of it and what goes there instead.
 *
 * A removal puts nothing there, and an insertion is a removal of nothing, so
 * both the notation coming out and the marker going in are the same shape and
 * are applied by the same rule.
 */
final class Edit
{
    /**
     * @param Region $region Stretch of the source being replaced
     * @param string $text   What replaces it, empty where it is simply removed
     */
    public function __construct(
        public readonly Region $region,
        public readonly string $text = '',
    ) {
    }

    /**
     * @return bool Whether this takes something out rather than putting
     *              something in
     */
    public function removes(): bool
    {
        return $this->text === '';
    }

    /**
     * @param Edit $next Edit that follows this one in the source
     *
     * @return bool Whether the two are removals with nothing between them
     */
    public function touches(self $next): bool
    {
        return $this->removes() && $next->removes() && $next->region->from <= $this->region->to;
    }

    /**
     * @param Edit $next Edit this one touches
     *
     * @return self The two as one removal
     */
    public function mergedWith(self $next): self
    {
        return new self(new Region($this->region->from, max($this->region->to, $next->region->to)));
    }
}
