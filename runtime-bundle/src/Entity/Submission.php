<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One visitor's answer to a form on the site: an RSVP, a sign-up, an order, a message.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cadenza_submission')]
#[ORM\Index(columns: ['form_id'])]
class Submission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private bool $seen = false;

    /**
     * @param list<array{label: string, type: string, value: mixed}> $answers in the order of the form's fields
     */
    public function __construct(
        #[ORM\Column(length: 32)]
        private string $formId,
        #[ORM\Column(length: 200)]
        private string $formTitle,
        #[ORM\Column(type: Types::JSON)]
        private array $answers,
        #[ORM\Column]
        private int $people = 1,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFormId(): string
    {
        return $this->formId;
    }

    public function getFormTitle(): string
    {
        return $this->formTitle;
    }

    /**
     * @return list<array{label: string, type: string, value: mixed}>
     */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    public function answer(string $label): mixed
    {
        foreach ($this->answers as $answer) {
            if (0 === strcasecmp($answer['label'], $label)) {
                return $answer['value'];
            }
        }

        return null;
    }

    public function getPeople(): int
    {
        return $this->people;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isSeen(): bool
    {
        return $this->seen;
    }

    public function markSeen(): void
    {
        $this->seen = true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'form' => $this->formId,
            'at' => $this->createdAt->format('Y-m-d H:i'),
            'people' => $this->people,
            'answers' => array_column($this->answers, 'value', 'label'),
        ];
    }
}
