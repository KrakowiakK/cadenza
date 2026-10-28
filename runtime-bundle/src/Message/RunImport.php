<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Message;

/**
 * Command: run an import job in the background (from a connected account, or from an uploaded export file).
 */
final readonly class RunImport
{
    public function __construct(public int $jobId)
    {
    }
}
