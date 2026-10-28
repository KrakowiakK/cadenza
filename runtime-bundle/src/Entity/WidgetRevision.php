<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One version of a widget's code, kept so that the live site keeps showing the version the owner approved while a
 * sketch shows the new one (a page pins the version it shows, see the "widget.use" operation).
 */
#[ORM\Entity]
#[ORM\Table(name: 'cadenza_widget_revision')]
#[ORM\UniqueConstraint(columns: ['widget', 'version'])]
class WidgetRevision
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
        #[ORM\Column]
        private int $version,
        #[ORM\Column(length: 80)]
        private string $label,
        #[ORM\Column(type: Types::TEXT)]
        private string $code,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCode(): string
    {
        return $this->code;
    }
}
