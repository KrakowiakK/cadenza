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
    public ?string $draftSummary = null;

    /** @var array<string, mixed> */
    public array $document = [];

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
