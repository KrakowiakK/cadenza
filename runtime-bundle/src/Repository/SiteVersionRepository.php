<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Repository;

use Cadenza\Runtime\Entity\SiteVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteVersion>
 */
final class SiteVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteVersion::class);
    }

    public function current(): ?SiteVersion
    {
        return $this->findOneBy(['status' => SiteVersion::PUBLISHED], ['number' => 'DESC']);
    }

    public function byNumber(int $number): ?SiteVersion
    {
        return $this->findOneBy(['status' => SiteVersion::PUBLISHED, 'number' => $number]);
    }

    public function draft(int $id): ?SiteVersion
    {
        $version = $this->find($id);

        return $version?->isDraft() ? $version : null;
    }

    /**
     * @return list<SiteVersion>
     */
    public function history(int $limit = 30): array
    {
        return $this->findBy(['status' => SiteVersion::PUBLISHED], ['number' => 'DESC'], $limit);
    }

    /**
     * Drafts waiting for a decision, newest first.
     *
     * @return list<SiteVersion>
     */
    public function drafts(int $limit = 40, bool $outsideOnly = false): array
    {
        $qb = $this->createQueryBuilder('v')->where('v.status = :s')->setParameter('s', SiteVersion::DRAFT)->orderBy('v.id', 'DESC')->setMaxResults($limit);
        if ($outsideOnly) {
            $qb->andWhere('v.author IS NOT NULL');
        }

        return $qb->getQuery()->getResult();
    }
}
