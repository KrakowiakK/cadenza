<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Visits of one page on one day — a counter only: no cookies, no addresses, nothing about the visitor.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cadenza_page_view')]
#[ORM\UniqueConstraint(columns: ['day', 'path'])]
class PageView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $day,
        #[ORM\Column(length: 191)]
        private string $path,
        #[ORM\Column]
        private int $views = 0,
    ) {
    }
}
