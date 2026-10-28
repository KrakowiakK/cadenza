<?php

declare(strict_types=1);

namespace App\Studio;

use App\Entity\Conversation;
use App\Entity\Proposal;
use App\Repository\ProposalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Turns operations into a proposal: a new draft in the application, or a revision of an open one.
 */
final class Proposals
{
    public function __construct(
        private readonly SiteClient $site,
        private readonly ProposalRepository $proposals,
        private readonly EntityManagerInterface $em,
        private readonly WorkflowInterface $proposalStateMachine,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $operations
     *
     * @return array{proposal: ?Proposal, errors: list<string>, created: list<string>}
     */
    public function propose(Conversation $conversation, array $operations, string $summary, ?int $reviseDraftId = null): array
    {
        $proposal = null;
        if (null !== $reviseDraftId) {
            $proposal = $this->proposals->byDraft($reviseDraftId);
            if (null === $proposal || !$proposal->isOpen()) {
                $proposal = null;
                $reviseDraftId = null;
            }
        }

        $result = $this->site->propose(array_values($operations), $summary, $reviseDraftId);
        if (!($result['ok'] ?? false)) {
            return ['proposal' => null, 'errors' => array_values((array) ($result['errors'] ?? ['unknown error'])), 'created' => []];
        }

        $focus = (string) ($result['focusPath'] ?? '/');
        if (null === $proposal) {
            $proposal = new Proposal($conversation, (int) $result['draftId'], $summary, $result['changes'] ?? [], $focus);
            $this->em->persist($proposal);
            $this->proposalStateMachine->apply($proposal, 'sketch');
        } else {
            $proposal->revise($summary, $result['changes'] ?? [], $focus);
            $this->proposalStateMachine->apply($proposal, 'revise');
        }
        $this->em->flush();

        return ['proposal' => $proposal, 'errors' => [], 'created' => array_values((array) ($result['created'] ?? []))];
    }
}
