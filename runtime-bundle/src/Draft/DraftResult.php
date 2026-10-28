<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Draft;

use Cadenza\Runtime\Entity\SiteVersion;
use Cadenza\Runtime\Site\Diff;

final readonly class DraftResult
{
    /**
     * @param list<string> $errors
     * @param list<string> $created
     */
    private function __construct(
        public ?SiteVersion $draft,
        public ?Diff $diff,
        public array $errors,
        public array $created,
    ) {
    }

    /**
     * @param list<string> $created
     */
    public static function valid(SiteVersion $draft, Diff $diff, array $created): self
    {
        return new self($draft, $diff, [], $created);
    }

    /**
     * @param list<string> $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(null, null, $errors, []);
    }

    public function isValid(): bool
    {
        return null !== $this->draft;
    }
}
