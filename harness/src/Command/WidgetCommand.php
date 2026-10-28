<?php

declare(strict_types=1);

namespace App\Command;

use App\Studio\WidgetProgrammer;
use App\Studio\WidgetWorkshop;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Writes a widget without the chat — to compare models, or to rebuild one by hand.
 */
#[AsCommand('cadenza:widget', 'Write a widget from a brief and save it on the site')]
final class WidgetCommand
{
    public function __construct(
        private readonly WidgetWorkshop $workshop,
        #[Autowire(service: 'ai.platform.generic.default')]
        private readonly PlatformInterface $platform,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('lowercase name, e.g. "frogger"')] string $name,
        #[Argument('what it should be and do')] string $brief,
        #[Option('wide, square, tall, banner, auto or background')] string $shape = 'wide',
        #[Option('another model of the same API, e.g. "gpt-oss:120b"')] ?string $model = null,
        #[Option('language of its texts')] string $language = 'pl',
    ): int {
        $started = microtime(true);
        $r = $this->workshop->build($name, $brief, ucfirst(str_replace('-', ' ', $name)), $shape, $language, static fn (string $step) => $io->comment($step),
            null !== $model ? new WidgetProgrammer($this->platform, $model) : null);
        if (!$r['ok']) {
            $io->error($r['errors']);

            return 1;
        }
        $io->success(\sprintf('%s v%d: %d KB in %d s%s', $name, $r['version'], \strlen($r['code']) >> 10, microtime(true) - $started,
            null !== $r['findings'] ? "\nstill:\n".$r['findings'] : ($r['tested'] ? ', tried in a browser: works' : '')));

        return 0;
    }
}
