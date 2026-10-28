<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Mcp\Agent;

use Cadenza\Runtime\Media\MediaLibrary;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * The application's own image library: every picture on the site lives here, with its author and licence.
 */
final class MediaTools
{
    public function __construct(private readonly MediaLibrary $media)
    {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'media_import', description: 'Download an image into the site\'s media library. Returns the site path (/media/…) to use in image props.')]
    public function import(
        #[Schema(description: 'http(s) URL of the image file')]
        string $url,
        #[Schema(description: 'Short description of what the image shows (alt text)')]
        string $alt = '',
        #[Schema(description: 'upload, wikimedia, pexels, unsplash, generated…')]
        string $source = 'upload',
        ?string $author = null,
        ?string $license = null,
        ?string $licenseUrl = null,
        ?string $sourceUrl = null,
    ): array {
        try {
            $media = $this->media->importUrl($url, ['alt' => $alt, 'source' => $source, 'author' => $author, 'license' => $license, 'licenseUrl' => $licenseUrl]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        $data = $media->toArray();
        if (null !== $sourceUrl) {
            $data['sourceUrl'] = $sourceUrl;
        }

        return ['ok' => true] + $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[McpTool(name: 'media_list', description: 'Images already in the site\'s media library, newest first.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function list(int $limit = 30): array
    {
        return array_map(static fn ($m): array => $m->toArray(), $this->media->recent($limit));
    }
}
