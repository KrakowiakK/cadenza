<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Proposal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Proposal>
 */
final class ProposalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Proposal::class);
    }

    /**
     * @return list<Proposal>
     */
    public function open(Conversation $conversation): array
    {
        return $this->findBy(['conversation' => $conversation, 'status' => ['drafted', 'sketched']], ['createdAt' => 'ASC']);
    }

    public function byDraft(int $draftId): ?Proposal
    {
        return $this->findOneBy(['draftId' => $draftId]);
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, Proposal>
     */
    public function byIds(array $ids): array
    {
        $out = [];
        foreach ($ids ? $this->findBy(['id' => $ids]) : [] as $proposal) {
            $out[$proposal->getId()] = $proposal;
        }

        return $out;
    }
}
