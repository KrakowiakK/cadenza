<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Operation;

final readonly class ApplyResult
{
    /**
     * @param array<string, mixed> $document
     * @param list<string>         $errors
     * @param list<string>         $created ids of pages and nodes created by the batch
     */
    public function __construct(
        public array $document,
        public array $errors,
        public array $created,
    ) {
    }

    public function isValid(): bool
    {
        return [] === $this->errors;
    }
}
