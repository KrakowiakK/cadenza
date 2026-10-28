<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The owner's account in another service. Credentials are stored encrypted and never leave the application.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cadenza_connection')]
class Connection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $connectedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSyncAt = null;

    public function __construct(
        #[ORM\Column(length: 64, unique: true)]
        private string $connector,
        #[ORM\Column(length: 255)]
        private string $account,
        #[ORM\Column(type: Types::TEXT)]
        private string $sealedCredentials,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $expiresAt,
    ) {
        $this->connectedAt = new \DateTimeImmutable();
    }

    public function getConnector(): string
    {
        return $this->connector;
    }

    public function getAccount(): string
    {
        return $this->account;
    }

    public function getSealedCredentials(): string
    {
        return $this->sealedCredentials;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function renew(string $sealedCredentials, ?\DateTimeImmutable $expiresAt, ?string $account = null): void
    {
        $this->sealedCredentials = $sealedCredentials;
        $this->expiresAt = $expiresAt;
        $this->account = $account ?? $this->account;
    }

    public function synced(): void
    {
        $this->lastSyncAt = new \DateTimeImmutable();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'connector' => $this->connector,
            'account' => $this->account,
            'connectedAt' => $this->connectedAt->format(\DATE_ATOM),
            'expiresAt' => $this->expiresAt?->format(\DATE_ATOM),
            'lastSyncAt' => $this->lastSyncAt?->format(\DATE_ATOM),
        ];
    }
}
