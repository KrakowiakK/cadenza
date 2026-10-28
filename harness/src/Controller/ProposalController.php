<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Proposal;
use App\Repository\ProposalRepository;
use App\Studio\SiteClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * The owner's decisions. These endpoints are the only path to the application's "control" MCP server.
 */
#[AsController]
final class ProposalController
{
    public function __construct(
        private readonly ProposalRepository $proposals,
        private readonly SiteClient $site,
        private readonly WorkflowInterface $proposalStateMachine,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/proposals/{id}/{decision}', requirements: ['decision' => 'approve|reject'], methods: ['POST'])]
    public function decide(string $id, string $decision): JsonResponse
    {
        $proposal = $this->proposals->find($id);

        return null === $proposal ? new JsonResponse(['error' => 'Unknown proposal.'], 404) : $this->apply($proposal, $decision);
    }

    /**
     * The owner keeps one variant: the others are discarded, the kept one stays a sketch to approve or refine.
     */
    #[Route('/api/variants/{group}/pick', methods: ['POST'])]
    public function pickVariant(string $group, \Symfony\Component\HttpFoundation\Request $request): JsonResponse
    {
        $keep = (string) ($request->toArray()['proposalId'] ?? '');
        $kept = null;
        foreach ($this->proposals->findBy(['variantGroup' => $group]) as $proposal) {
            if ($proposal->getId() === $keep) {
                $kept = $proposal;
            } elseif ($this->proposalStateMachine->can($proposal, 'reject')) {
                $this->site->discard($proposal->getDraftId());
                $this->proposalStateMachine->apply($proposal, 'reject');
            }
        }
        $this->em->flush();

        return null === $kept ? new JsonResponse(['error' => 'Unknown variant.'], 404) : new JsonResponse(['ok' => true, 'proposal' => $kept->toArray()]);
    }

    #[Route('/api/drafts/{draftId}/{decision}', requirements: ['draftId' => '\d+', 'decision' => 'approve|reject'], methods: ['POST'])]
    public function decideDraft(int $draftId, string $decision): JsonResponse
    {
        $proposal = $this->proposals->byDraft($draftId);
        if (null !== $proposal) {
            return $this->apply($proposal, $decision);
        }
        // A draft made outside a conversation (e.g. bin/console cadenza:propose).
        $result = 'approve' === $decision ? $this->site->publish($draftId) : $this->site->discard($draftId);

        return new JsonResponse($result + ['path' => null], ($result['ok'] ?? false) ? 200 : 409);
    }

    private function apply(Proposal $proposal, string $decision): JsonResponse
    {
        if (!$this->proposalStateMachine->can($proposal, $decision)) {
            return new JsonResponse(['error' => \sprintf('The proposal is %s; it cannot be %sd.', $proposal->getStatus(), $decision), 'proposal' => $proposal->toArray()], 409);
        }
        if ('approve' === $decision) {
            $result = $this->site->publish($proposal->getDraftId());
            if (!($result['ok'] ?? false)) {
                return new JsonResponse(['error' => $result['error'] ?? 'Publishing failed.', 'proposal' => $proposal->toArray()], 409);
            }
            $proposal->markPublished((int) $result['version']);
        } else {
            $this->site->discard($proposal->getDraftId());
        }
        $this->proposalStateMachine->apply($proposal, $decision);
        $this->em->flush();

        return new JsonResponse(['ok' => true, 'proposal' => $proposal->toArray(), 'path' => $proposal->getFocusPath()]);
    }
}
