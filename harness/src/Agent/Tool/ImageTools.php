<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Media\ImageGenerator;
use App\Media\ImageOffers;
use App\Media\ImagePlacer;
use App\Media\ImageSearch;
use App\Studio\Turn;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('find_images', 'Search freely licensed photos (Wikimedia Commons; Pexels/Unsplash when configured). Returns candidates with ids for use_image. Search in English for best results.', method: 'find')]
#[AsTool('offer_images', 'Show the owner a grid of photo candidates for one image slot of a node in the sketch; the owner picks and the sketch updates by itself. Best way to choose photos together.', method: 'offer')]
#[AsTool('use_image', 'Import an image (a find_images candidate id, an attached upload id, or a generated image id) into the site media library. Returns the /media/… path to put in image props.', method: 'use')]
#[AsTool('generate_image', 'Generate an image from a description (FLUX via Hugging Face). Returns the /media/… path. Use for illustrations or when no fitting photo exists.', method: 'generate')]
final class ImageTools
{
    public function __construct(
        private readonly ImageSearch $search,
        private readonly ImageOffers $offers,
        private readonly ImagePlacer $placer,
        private readonly ImageGenerator $generator,
        private readonly Turn $turn,
    ) {
    }

    /**
     * @param string $query       What the photo should show, in English, e.g. "cozy coffee shop interior"
     * @param int    $count       How many candidates (1-10)
     * @param string $orientation landscape, portrait, square or any
     */
    public function find(string $query, int $count = 6, string $orientation = 'any'): string
    {
        $results = $this->search->search($query, max(1, min(10, $count)), $orientation);
        if ([] === $results) {
            return 'No photos found. Try a simpler English query, or generate_image.';
        }
        $this->offers->rememberCandidates($results);

        return implode("\n", array_map(static fn (array $r): string => \sprintf('- %s: "%s" %dx%d, %s by %s', $r['id'], mb_substr($r['title'], 0, 80), $r['width'], $r['height'], $r['license'] ?? '?', mb_substr((string) ($r['author'] ?? 'unknown'), 0, 40)), $results));
    }

    /**
     * @param string $query       What the photo should show, in English
     * @param string $nodeId      Node that gets the image (from the sketch you proposed)
     * @param string $prop        Image prop of the node: "image" (hero, card), "src" (image), "backgroundImage" (section) or "images" (gallery, appends)
     * @param string $label       Short label for the owner, in their language, e.g. "Zdjęcie w nagłówku"
     * @param string $orientation landscape, portrait, square or any
     */
    public function offer(string $query, string $nodeId, string $prop, string $label, string $orientation = 'landscape'): string
    {
        $results = $this->search->search($query, 8, $orientation);
        if ([] === $results) {
            return 'No photos found for this query; try another English query or generate_image.';
        }
        $draftId = $this->turn->proposals ? end($this->turn->proposals)->getDraftId() : $this->turn->viewedDraftId;
        $offerId = $this->offers->create([
            'nodeId' => $nodeId,
            'prop' => $prop,
            'label' => $label,
            'query' => $query,
            'draftId' => $draftId,
            'conversationId' => $this->turn->conversation?->getId(),
            'candidates' => $results,
        ]);
        $this->turn->emit('choice', [
            'offerId' => $offerId,
            'label' => $label,
            'candidates' => array_map(static fn (array $r): array => array_intersect_key($r, array_flip(['id', 'thumb', 'title', 'author', 'license', 'source', 'width', 'height'])), $results),
        ]);

        return \sprintf('Showed %d photos to the owner for "%s". When they pick one it goes into the sketch automatically; tell them to pick.', \count($results), $label);
    }

    /**
     * @param string $imageId Candidate id from find_images (e.g. "wm-12345"), or an attachment/generated id (e.g. "01j….jpg")
     * @param string $alt     Short description of the image for accessibility, in the site's language
     */
    public function use(string $imageId, string $alt = ''): string
    {
        $media = $this->placer->import($imageId, $alt);
        if (!($media['ok'] ?? false)) {
            return 'Import failed: '.($media['error'] ?? 'unknown error');
        }

        return \sprintf('Imported as %s (%s, %sx%s). Use this path in image props.', $media['path'], $media['orientation'] ?? '', $media['width'] ?? '?', $media['height'] ?? '?');
    }

    /**
     * @param string $prompt      Detailed description in English: subject, mood, light, colours matching the site theme, style (photo / illustration)
     * @param string $orientation landscape, portrait or square
     */
    public function generate(string $prompt, string $orientation = 'landscape'): string
    {
        if (!$this->generator->isAvailable()) {
            return 'Image generation is not configured on this installation. Use find_images / offer_images, or a "stub" node.';
        }
        $upload = $this->generator->generate($prompt, $orientation);
        $media = $this->placer->import($upload['id'], 'generated: '.mb_substr($prompt, 0, 120));
        if (!($media['ok'] ?? false)) {
            return 'Generated, but import failed: '.($media['error'] ?? 'unknown error');
        }
        $this->turn->emit('generated', ['url' => $upload['url'], 'prompt' => $prompt]);

        return \sprintf('Generated and imported as %s.', $media['path']);
    }
}
