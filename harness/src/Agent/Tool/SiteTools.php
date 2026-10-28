<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Entity\Proposal;
use App\Repository\ProposalRepository;
use App\Studio\SiteClient;
use App\Studio\Turn;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsTool('propose_changes', 'Propose a change to the site as a batch of operations. It becomes a sketch the owner sees on the page immediately and approves or rejects. Returns the result, or errors to fix and retry.', method: 'propose')]
#[AsTool('read_page', 'Full JSON of one page with every prop value (the outline in your instructions shows only key props).', method: 'readPage')]
#[AsTool('read_outline', 'Fresh outline of the live site, or of an open draft when draftId is given.', method: 'readOutline')]
final class SiteTools
{
    public function __construct(
        private readonly SiteClient $site,
        private readonly Turn $turn,
        private readonly ProposalRepository $proposals,
        private readonly EntityManagerInterface $em,
        private readonly WorkflowInterface $proposalStateMachine,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $operations Operations of the editing language (see the guide), e.g. [{"op": "node.update", "id": "nab12c", "props": {"title": "…"}}]
     * @param string                     $summary    One short sentence in the owner's language saying what the sketch shows
     * @param int|null                   $reviseDraftId Id of an open draft of this conversation to extend with more operations (to adjust a sketch the owner is looking at); omit to start a new, separate proposal
     */
    public function propose(array $operations, string $summary, ?int $reviseDraftId = null): string
    {
        $proposal = null;
        if (null !== $reviseDraftId) {
            $proposal = $this->proposals->byDraft($reviseDraftId);
            if (null === $proposal || !$proposal->isOpen()) {
                return \sprintf('Draft %d is not open any more; omit reviseDraftId to start a new proposal.', $reviseDraftId);
            }
        }

        $result = $this->site->propose(array_values($operations), $summary, $reviseDraftId);
        if (!($result['ok'] ?? false)) {
            return "The site rejected the batch, nothing was saved. Fix these errors and call propose_changes again with the whole corrected batch:\n- ".implode("\n- ", (array) ($result['errors'] ?? ['unknown error']));
        }

        $draftId = (int) $result['draftId'];
        $focus = (string) ($result['focusPath'] ?? '/');
        if (null === $proposal) {
            $proposal = new Proposal($this->turn->conversation, $draftId, $summary, $result['changes'] ?? [], $focus);
            $this->em->persist($proposal);
            $this->proposalStateMachine->apply($proposal, 'sketch');
        } else {
            $proposal->revise($summary, $result['changes'] ?? [], $focus);
            $this->proposalStateMachine->apply($proposal, 'revise');
        }
        $this->em->flush();

        $this->turn->proposals[] = $proposal;
        $this->turn->emit('proposal', $proposal->toArray());
        $this->turn->emit('ui', ['command' => 'showSketch', 'draftId' => $draftId, 'path' => $focus]);

        return \sprintf(
            "Sketch ready: draft %d, shown to the owner on %s. Changes: %s. New ids: %s.\nThe owner now reviews it and approves or rejects it in the panel; do not claim it is live.",
            $draftId,
            $focus,
            json_encode($result['changes'] ?? []),
            implode(', ', \array_slice((array) ($result['createdIds'] ?? []), 0, 30)) ?: 'none',
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
