<?php

declare(strict_types=1);

namespace Cadenza\Runtime\MessageHandler;

use Cadenza\Runtime\Message\BlockDefined;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * A new block may use Tailwind classes the site's stylesheet does not have yet: build it again (in the background).
 * Templates are mirrored to var/cadenza/blocks, which the app's stylesheet lists as a @source. Sites without the
 * Tailwind bundle simply skip this.
 */
#[AsMessageHandler]
final class RebuildBlockStyles
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(BlockDefined $event): void
    {
        $console = $this->projectDir.'/bin/console';
        if ('test' === $this->environment || !is_file($console)) {
            return;
        }
        $process = new Process([(new PhpExecutableFinder())->find() ?: 'php', $console, 'tailwind:build', '--env='.$this->environment], $this->projectDir, null, null, 120);
        $process->run();
        if (!$process->isSuccessful()) {
            $this->logger?->warning('Styles for block {name} not rebuilt: {error}', ['name' => $event->name, 'error' => mb_substr($process->getErrorOutput() ?: $process->getOutput(), 0, 500)]);
        }
    }
}
