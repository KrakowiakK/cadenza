<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Media\UploadStore;
use App\Studio\SiteClient;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('add_document', 'Put a document the owner attached (PDF, DOCX, ODT, PPTX, DOC, TXT) on the site for visitors to download. Returns its path (/files/…) for a "downloads" node item or a button link.', method: 'add')]
final class DocumentTools
{
    public function __construct(
        private readonly SiteClient $site,
        private readonly UploadStore $uploads,
    ) {
    }

    /**
     * @param string $uploadId Id of the attached document, e.g. "01j….pdf"
     * @param string $name     File name visitors will download, with the extension, e.g. "Menu-zima-2026.pdf"
     */
    public function add(string $uploadId, string $name = ''): string
    {
        if (null === $this->uploads->path($uploadId)) {
            return 'Unknown upload id; use the id from the attachment.';
        }
        $extension = pathinfo($uploadId, \PATHINFO_EXTENSION);
        $name = '' !== trim($name) ? trim($name) : 'document.'.$extension;
        if (strtolower(pathinfo($name, \PATHINFO_EXTENSION)) !== $extension) {
            $name .= '.'.$extension;
        }
        $r = $this->site->importFile($this->uploads->url($uploadId), $name);
        if (!($r['ok'] ?? false)) {
            return 'Not stored: '.($r['error'] ?? 'unknown error');
        }

        return \sprintf('Stored as %s (%s, %d kB%s). Use it as "file" in a downloads node item, or as a button href.', $r['path'], $r['type'], (int) round($r['size'] / 1024), isset($r['pages']) ? ', '.$r['pages'].' pages' : '');
    }
}
