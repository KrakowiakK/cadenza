<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Someone who can sign in to the site: the owner (ROLE_OWNER — the studio, sketches, dashboard) and, when the owner
 * wants a members' area, its members.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cadenza_account')]
class Account implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const OWNER = 'ROLE_OWNER';
    public const MEMBER = 'ROLE_MEMBER';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    /**
     * @param list<string> $roles
     */
    public function __construct(
        #[ORM\Column(length: 180, unique: true)]
        private string $email,
        #[ORM\Column(type: Types::JSON)]
        private array $roles,
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $name = null,
    ) {
        $this->email = mb_strtolower(trim($email));
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    public function isOwner(): bool
    {
        return \in_array(self::OWNER, $this->roles, true);
    }

    public function grant(string $role): void
    {
        if (!\in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $hash): void
    {
        $this->password = $hash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function signedIn(): void
    {
        $this->lastLoginAt = new \DateTimeImmutable();
    }

    public function __serialize(): array
    {
        // The session keeps a hash of the password hash, never the hash itself (Symfony 7.3+ practice).
        $data = (array) $this;
        $data["\0".self::class."\0password"] = null !== $this->password ? hash('crc32c', $this->password) : null;

        return $data;
    }
}
