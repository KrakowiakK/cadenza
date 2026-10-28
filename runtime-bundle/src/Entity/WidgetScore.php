<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A result a visitor sent from a game widget, for the site's shared leaderboard.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cadenza_widget_score')]
#[ORM\Index(columns: ['widget', 'score'])]
class WidgetScore
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Column(length: 40)]
        private string $widget,
        #[ORM\Column(length: 24)]
        private string $player,
        #[ORM\Column]
        private int $score,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @return array{player: string, score: int, at: string}
     */
    public function toArray(): array
    {
        return ['player' => $this->player, 'score' => $this->score, 'at' => $this->createdAt->format(\DATE_ATOM)];
    }
}
