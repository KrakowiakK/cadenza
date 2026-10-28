<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Message;

/**
 * Event: an item from an outside service is now in the media library. Blocks react to it (albums, tagging…).
 */
final readonly class MediaImported
{
    public function __construct(
        public int $mediaId,
        public string $source,
        public string $externalId,
        public int $jobId,
    ) {
    }
}
