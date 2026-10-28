<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProposalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

/**
 * A change Cadenza proposes to the owner: a draft in the user's application, shown as a sketch, waiting for a decision.
 * Its life is the "proposal" state machine (config/packages/workflow.yaml); only the owner can approve.
 */
#[ORM\Entity(repositoryClass: ProposalRepository::class)]
class Proposal
{
    #[ORM\Id]
    #[ORM\Column(length: 26)]
    private string $id;

    #[ORM\Column(length: 16)]
    private string $status = 'drafted';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $publishedVersion = null;

    /** Proposals offered together as alternatives ("variants") share a group; the owner keeps one. */
    #[ORM\Column(length: 26, nullable: true)]
    private ?string $variantGroup = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $variantName = null;

    /**
     * @param array<string, int|bool> $changes
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Conversation $conversation,
        #[ORM\Column]
        private int $draftId,
        #[ORM\Column(length: 255)]
        private string $summary,
        #[ORM\Column(type: Types::JSON)]
        private array $changes,
        #[ORM\Column(length: 255)]
        private string $focusPath,
    ) {
        $this->id = (string) new Ulid();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getConversation(): Conversation
    {
        return $this->conversation;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDraftId(): int
    {
        return $this->draftId;
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getFocusPath(): string
    {
        return $this->focusPath;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        if (\in_array($status, ['applied', 'rejected'], true)) {
            $this->decidedAt = new \DateTimeImmutable();
        }
    }

    public function isOpen(): bool
    {
        return \in_array($this->status, ['drafted', 'sketched'], true);
    }

    /**
     * @param array<string, int|bool> $changes
     */
    public function revise(string $summary, array $changes, string $focusPath): void
    {
        $this->summary = $summary;
        $this->changes = $changes;
        $this->focusPath = $focusPath;
    }

    public function asVariant(string $group, string $name): void
    {
        $this->variantGroup = $group;
        $this->variantName = mb_substr($name, 0, 80);
    }

    public function getVariantGroup(): ?string
    {
        return $this->variantGroup;
    }

    public function markPublished(int $version): void
    {
        $this->publishedVersion = $version;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'proposalId' => $this->id,
            'draftId' => $this->draftId,
            'summary' => $this->summary,
            'changes' => $this->changes,
            'focusPath' => $this->focusPath,
            'status' => $this->status,
            'version' => $this->publishedVersion,
            'variantGroup' => $this->variantGroup,
            'variantName' => $this->variantName,
        ];
    }
}
