<?php

declare(strict_types=1);

namespace App\Media;

use App\Entity\Conversation;
use App\Entity\Proposal;
use App\Studio\Proposals;
use App\Studio\SiteClient;

/**
 * Brings an image into the site's media library and puts it into a node of the sketch.
 */
final class ImagePlacer
{
    public function __construct(
        private readonly SiteClient $site,
        private readonly ImageOffers $offers,
        private readonly UploadStore $uploads,
        private readonly Proposals $proposals,
    ) {
    }

    /**
     * @return array<string, mixed> media data with "path", or ["ok" => false, "error" => …]
     */
    public function import(string $imageId, string $alt = ''): array
    {
        if (null !== $this->uploads->path($imageId)) {
            return $this->site->importMedia($this->uploads->url($imageId), ['alt' => $alt, 'source' => str_contains($alt, 'generated') ? 'generated' : 'upload']);
        }
        $candidate = $this->offers->candidate($imageId);
        if (null === $candidate) {
            return ['ok' => false, 'error' => \sprintf('Unknown image "%s": use an id from find_images or an attachment id.', $imageId)];
        }

        return $this->site->importMedia($candidate['url'], [
            'alt' => $alt ?: $candidate['title'],
            'source' => $candidate['source'],
            'author' => $candidate['author'] ?? null,
            'license' => $candidate['license'] ?? null,
            'licenseUrl' => $candidate['licenseUrl'] ?? null,
            'sourceUrl' => $candidate['sourceUrl'] ?? null,
        ]);
    }

    /**
     * Sets (or, for lists of images, appends) the image on the node, revising the open draft or opening a new proposal.
     *
     * @param array<string, mixed> $media
     *
     * @return array{proposal: ?Proposal, errors: list<string>}
     */
    public function place(Conversation $conversation, ?int $draftId, string $nodeId, string $prop, array $media, string $summary): array
    {
        $node = $this->site->node($nodeId, $draftId);
        if (null === $node) {
            return ['proposal' => null, 'errors' => [\sprintf('Node %s does not exist any more.', $nodeId)]];
        }
        if ('images' === $prop) {
            $images = $node['props']['images'] ?? [];
            $images[] = array_filter(['src' => $media['path'], 'alt' => $media['alt'] ?? '']);
            $props = ['images' => $images];
        } else {
            $props = [$prop => $media['path']];
            if ('image' === $node['type'] && 'src' === $prop && !empty($media['alt'])) {
                $props['alt'] = $media['alt'];
            }
        }
        $result = $this->proposals->propose($conversation, [['op' => 'node.update', 'id' => $nodeId, 'props' => $props]], $summary, $draftId);

        return ['proposal' => $result['proposal'], 'errors' => $result['errors']];
    }
}
