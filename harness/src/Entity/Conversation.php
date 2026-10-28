<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ConversationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * A conversation between the owner and Cadenza. Turns keep what was said and which proposals came out of it.
 */
#[ORM\Entity(repositoryClass: ConversationRepository::class)]
class Conversation
{
    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @var list<array{role: 'user'|'assistant', text: string, selection?: list<array<string, string>>, proposals?: list<string>, path?: string, at: string}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $turns = [];

    public function __construct()
    {
        $this->id = (string) new Ulid();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getTurns(): array
    {
        return $this->turns;
    }

    /**
     * @param list<array<string, string>> $selection
     */
    public function addUserTurn(string $text, array $selection, string $path, array $attachments = []): void
    {
        $this->turns[] = ['role' => 'user', 'text' => $text, 'selection' => $selection, 'attachments' => $attachments, 'path' => $path, 'at' => date(\DATE_ATOM)];
    }

    /**
     * @param list<string> $proposalIds
     */
    public function addAssistantTurn(string $text, array $proposalIds): void
    {
        $this->turns[] = ['role' => 'assistant', 'text' => $text, 'proposals' => $proposalIds, 'at' => date(\DATE_ATOM)];
    }
}
