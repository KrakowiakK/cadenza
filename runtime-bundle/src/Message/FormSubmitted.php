<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Message;

/**
 * Event: a visitor sent a form on the site. Blocks react to it (notify the owner, add to a mailing list…).
 */
final readonly class FormSubmitted
{
    public function __construct(
        public int $submissionId,
        public string $formId,
        public string $formTitle,
    ) {
    }
}
