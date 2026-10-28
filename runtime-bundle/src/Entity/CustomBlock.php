<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A block type the agent wrote for this site when no built-in pattern fitted: a prop schema and a Twig template,
 * rendered in a sandbox and sanitised. Every change is a new version.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cadenza_custom_block')]
class CustomBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param array<string, array<string, mixed>> $props
     */
    public function __construct(
        #[ORM\Column(length: 40, unique: true)]
        private string $name,
        #[ORM\Column(length: 80)]
        private string $label,
        #[ORM\Column(type: Types::TEXT)]
        private string $description,
        #[ORM\Column(type: Types::JSON)]
        private array $props,
        #[ORM\Column(type: Types::TEXT)]
        private string $template,
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @param array<string, array<string, mixed>> $props
     */
    public function revise(string $label, string $description, array $props, string $template): void
    {
        [$this->label, $this->description, $this->props, $this->template] = [$label, $description, $props, $template];
        ++$this->version;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getProps(): array
    {
        return $this->props;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
