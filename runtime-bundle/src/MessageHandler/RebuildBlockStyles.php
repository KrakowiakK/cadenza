<?php

declare(strict_types=1);

namespace Cadenza\Runtime\MessageHandler;

use Cadenza\Runtime\Message\BlockDefined;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A new block may use Tailwind classes the site's stylesheet does not have yet: build it again (in the background).
 * Templates are mirrored to var/cadenza/blocks, which the app's stylesheet lists as a @source.
 */
#[AsMessageHandler]
final class RebuildBlockStyles
{
    public function __construct(
        #[Autowire('@?.tailwind.builder')]
        private readonly ?object $tailwind = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(BlockDefined $event): void
    {
        if (null === $this->tailwind || !method_exists($this->tailwind, 'runBuild')) {
            return;
        }
        $process = $this->tailwind->runBuild(false, false, false);
        $process->wait();
        if (!$process->isSuccessful()) {
            $this->logger?->warning('Styles for block {name} not rebuilt: {error}', ['name' => $event->name, 'error' => $process->getErrorOutput()]);
        }
    }
}
