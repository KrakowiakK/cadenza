<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Connector;

use Cadenza\Runtime\Entity\Media;
use Cadenza\Runtime\Media\MediaLibrary;
use Cadenza\Runtime\Message\MediaImported;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * What a connector may do while importing: store an item once (deduplicated by its external id) and announce it.
 */
final class ImportContext
{
    public function __construct(
        private readonly MediaLibrary $media,
        private readonly MessageBusInterface $bus,
        public readonly string $source,
        public readonly int $jobId,
    ) {
    }

    public function has(string $externalId): bool
    {
        return null !== $this->media->findExternal($this->source, $externalId);
    }

    /**
     * @param list<string> $tags
     */
    public function addImage(string $bytes, string $externalId, ?string $caption, ?\DateTimeImmutable $takenAt, array $tags = [], ?string $permalink = null, ?string $author = null): Media
    {
        $media = $this->media->store($bytes, [
            'alt' => mb_substr(trim(preg_replace('/#\S+/u', '', (string) $caption) ?? ''), 0, 200),
            'source' => $this->source,
            'sourceUrl' => $permalink,
            'author' => $author,
        ], fn (Media $m) => $m->describe($externalId, $caption, $takenAt, $tags));
        $this->bus->dispatch(new MediaImported((int) $media->getId(), $this->source, $externalId, $this->jobId));

        return $media;
    }

    /**
     * @return list<string>
     */
    public static function hashtags(?string $text): array
    {
        preg_match_all('/#([\p{L}\p{N}_]+)/u', (string) $text, $m);

        return $m[1];
    }
}
