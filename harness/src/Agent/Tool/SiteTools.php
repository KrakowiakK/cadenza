<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Studio\Proposals;
use App\Studio\SiteClient;
use App\Studio\Turn;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('propose_changes', 'Propose a change to the site as a batch of operations. It becomes a sketch the owner sees on the page immediately and approves or rejects. Returns the result, or errors to fix and retry.', method: 'propose')]
#[AsTool('read_page', 'Full JSON of one page with every prop value (the outline in your instructions shows only key props).', method: 'readPage')]
#[AsTool('read_outline', 'Fresh outline of the live site, or of an open draft when draftId is given.', method: 'readOutline')]
final class SiteTools
{
    public function __construct(
        private readonly SiteClient $site,
        private readonly Turn $turn,
        private readonly Proposals $proposals,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $operations Operations of the editing language (see the guide), e.g. [{"op": "node.update", "id": "nab12c", "props": {"title": "…"}}]
     * @param string                     $summary    One short sentence in the owner's language saying what the sketch shows
     * @param int|null                   $reviseDraftId Id of an open draft of this conversation to extend with more operations (to adjust a sketch the owner is looking at); omit to start a new, separate proposal
     */
    public function propose(array $operations, string $summary, ?int $reviseDraftId = null): string
    {
        $result = $this->proposals->propose($this->turn->conversation, $operations, $summary, $reviseDraftId);
        if (null === $result['proposal']) {
            return "The site rejected the batch, nothing was saved. Fix these errors and call propose_changes again with the whole corrected batch:\n- ".implode("\n- ", $result['errors']);
        }
        $proposal = $result['proposal'];

        $this->turn->proposals[] = $proposal;
        $this->turn->emit('proposal', $proposal->toArray());
        $this->turn->emit('ui', ['command' => 'showSketch', 'draftId' => $proposal->getDraftId(), 'path' => $proposal->getFocusPath()]);

        return \sprintf(
            "Sketch ready: draft %d, shown to the owner on %s. Changes: %s.\nCreated: %s\nThe owner now reviews it and approves or rejects it in the panel; do not claim it is live.",
            $proposal->getDraftId(),
            $proposal->getFocusPath(),
            json_encode($proposal->toArray()['changes']),
            implode('; ', \array_slice($result['created'], 0, 40)) ?: 'nothing new',
        );
    }

    /**
     * @param string   $page    Page path like "/" or "/menu"
     * @param int|null $draftId Read the page as it is in this draft
     */
    public function readPage(string $page, ?int $draftId = null): string
    {
        return $this->site->pageTree($page, $draftId);
    }

    /**
     * @param int|null $draftId Outline of this draft instead of the live site
     */
    public function readOutline(?int $draftId = null): string
    {
        return $this->site->outline($draftId);
    }
}
