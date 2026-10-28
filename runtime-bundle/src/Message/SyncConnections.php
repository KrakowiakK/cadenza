<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Message;

/**
 * Command (scheduled daily): refresh tokens that will expire soon and import new items from connected accounts.
 */
final readonly class SyncConnections
{
}
