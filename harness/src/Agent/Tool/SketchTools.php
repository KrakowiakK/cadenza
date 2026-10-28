<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Repository\ProposalRepository;
use App\Studio\SiteClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Sketches waiting for the owner, from every conversation: the owner asks what is pending, or to clear out old ones.
 */
#[AsTool('pending_sketches', 'Every sketch still waiting for the owner\'s approval, from all conversations (newest first): draft id, what it changes, its page, when. Read it when the owner asks what is waiting, or before clearing sketches out.', method: 'pending')]
#[AsTool('discard_sketches', 'Throws sketches away (they never go live; this cannot be undone). Only the ones the owner asked to drop: by draft ids from pending_sketches, or "olderThanDays" for everything older than that. Approving is the owner\'s own click in the panel — you cannot approve.', method: 'discard')]
final class SketchTools
{
    public function __construct(
        private readonly ProposalRepository $proposals,
        private readonly SiteClient $site,
        private readonly EntityManagerInterface $em,
        #[Target('proposal')]
        private readonly WorkflowInterface $proposalStateMachine,
    ) {
    }

    public function pending(): string
    {
        $open = $this->proposals->allOpen();
        if ([] === $open) {
            return 'Nothing is waiting: there are no open sketches.';
        }
        $lines = array_map(static fn ($p): string => \sprintf('- draft %d (%s, page %s): %s', $p->getDraftId(), $p->getCreatedAt()->format('Y-m-d H:i'), $p->getFocusPath(), $p->getSummary()), \array_slice($open, 0, 60));

        return \sprintf("%d sketches wait for the owner:\n%s%s\nTell the owner in a few words how many and what they are about (grouped, not the whole list); they approve or reject them in the panel, or ask you to drop some.",
            \count($open), implode("\n", $lines), \count($open) > 60 ? "\n…and ".(\count($open) - 60).' older ones' : '');
    }

    /**
     * @param list<int> $draftIds      Drafts to throw away
     * @param int|null  $olderThanDays Or: every open sketch older than this many days
     */
    public function discard(array $draftIds = [], ?int $olderThanDays = null): string
    {
        $ids = array_map('intval', $draftIds);
        $limit = null !== $olderThanDays ? new \DateTimeImmutable(\sprintf('-%d days', max(0, $olderThanDays))) : null;
        $dropped = [];
        foreach ($this->proposals->allOpen() as $proposal) {
            if (!\in_array($proposal->getDraftId(), $ids, true) && (null === $limit || $proposal->getCreatedAt() >= $limit)) {
                continue;
            }
            $this->site->discard($proposal->getDraftId());
            if ($this->proposalStateMachine->can($proposal, 'reject')) {
                $this->proposalStateMachine->apply($proposal, 'reject');
            } else {
                $proposal->setStatus('rejected');
            }
            $dropped[] = $proposal->getDraftId();
        }
        $this->em->flush();

        return [] === $dropped ? 'No sketch matched; nothing was thrown away.' : \sprintf('Thrown away: %d sketches (drafts %s).', \count($dropped), implode(', ', $dropped));
    }
}
