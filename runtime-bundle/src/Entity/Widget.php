<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A living piece of a page the agent wrote with its own code — a game, a 3D scene, an animation, a calculator. It
 * runs in a sandboxed frame of its own, with no access to the site, its visitors' cookies or the network.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cadenza_widget')]
class Widget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** The last error a browser reported while the owner looked at it, for the agent to fix. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    /** The frame it was written for: wide, square, tall, banner, auto or background. */
    #[ORM\Column(length: 20, options: ['default' => 'wide'])]
    private string $shape = 'wide';

    public function __construct(
        #[ORM\Column(length: 40, unique: true)]
        private string $name,
        #[ORM\Column(length: 80)]
        private string $label,
        #[ORM\Column(type: Types::TEXT)]
        private string $description,
        #[ORM\Column(type: Types::TEXT)]
        private string $code,
    ) {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function revise(string $label, string $description, string $code): void
    {
        [$this->label, $this->description, $this->code] = [$label, $description, $code];
        ++$this->version;
        $this->lastError = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setShape(string $shape): void
    {
        $this->shape = $shape;
    }

    public function getShape(): string
    {
        return $this->shape;
    }

    public function reportError(string $error): void
    {
        $this->lastError = mb_substr($error, 0, 2000);
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
