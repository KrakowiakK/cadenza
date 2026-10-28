<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Twig;

use Cadenza\Runtime\Site\Diff;

/**
 * What the current request renders: the live site or a sketch (draft) with its differences marked.
 */
final class RenderState
{
    public ?Diff $diff = null;
    public ?int $draftId = null;
    public bool $owner = false;
    public bool $showBefore = false;
    /** Rendered inside a preview frame (variant thumbnails): no studio UI. */
    public bool $embed = false;
    public ?string $draftSummary = null;

    /** Published version shown read-only (timeline preview). */
    public ?\Cadenza\Runtime\Entity\SiteVersion $previewVersion = null;
    public int $currentNumber = 0;

    /** @var array<string, mixed> */
    public array $document = [];

    /** Language of the page being rendered (a translated page has its own), when known. */
    public ?string $pageLanguage = null;

    /** The visitor's browser language (pl or en): used while the site has not chosen one. */
    public string $visitorLanguage = 'en';

    /**
     * Two-letter language for texts of the site itself (forms, countdowns, sign-in): the page's, else the site's,
     * else the visitor's.
     */
    public function language(): string
    {
        return substr($this->pageLanguage ?? $this->document['language'] ?? $this->visitorLanguage, 0, 2);
    }

    public function isSketch(): bool
    {
        return null !== $this->diff;
    }

    public function stateOf(string $nodeId): ?string
    {
        if (null === $this->diff) {
            return null;
        }
        if ($this->showBefore) {
            return isset($this->diff->removed[$nodeId]) ? 'removing' : (('changed' === ($this->diff->nodes[$nodeId] ?? null)) ? 'changing' : null);
        }

        return $this->diff->nodes[$nodeId] ?? null;
    }
}
