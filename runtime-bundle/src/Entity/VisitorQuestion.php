<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A question a visitor asked the site in their own words, and whether the site's own content could answer it.
 * The ones it could not answer tell the owner what is missing from the site.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cadenza_visitor_question')]
#[ORM\Index(columns: ['created_at'])]
class VisitorQuestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Column(length: 320)]
        private string $question,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $answer,
        /** False when the site's content did not hold the answer. */
        #[ORM\Column]
        private bool $answered,
        #[ORM\Column(length: 255)]
        private string $path,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    public function isAnswered(): bool
    {
        return $this->answered;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['question' => $this->question, 'answered' => $this->answered, 'answer' => $this->answer, 'page' => $this->path, 'at' => $this->createdAt->format(\DATE_ATOM)];
    }
}
