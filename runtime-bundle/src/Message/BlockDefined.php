<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Message;

/**
 * Event: the agent wrote a new block type or a new version of one.
 */
final readonly class BlockDefined
{
    public function __construct(
        public string $name,
        public int $version,
    ) {
    }
}
