<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Console;

/**
 * The arguments a run was invoked with.
 *
 * Symfony reads `-o=build` as the value "=build", because a short option takes
 * everything that follows its letter. Long options are written `--out=build`,
 * so writing the same shape on the short one is a natural mistake and a silent
 * one: the compiler writes into a directory called "=" and reports success.
 * Splitting it here means every short option that takes a value accepts both
 * spellings, rather than `-o` being fixed and `-m` and `-f` left as traps.
 */
final class Argv
{
    /**
     * @param list<string> $tokens
     */
    public function __construct(
        private readonly array $tokens,
    ) {
    }

    /**
     * @return list<string>
     */
    public function normalised(): array
    {
        $normalised = [];
        $literal = false;

        foreach ($this->tokens as $token) {
            $literal = $literal || $token === '--';

            if ($literal || !preg_match('/^-([a-zA-Z])=(.*)$/', $token, $matches)) {
                $normalised[] = $token;

                continue;
            }

            $normalised[] = '-' . $matches[1];
            $normalised[] = $matches[2];
        }

        return $normalised;
    }
}
