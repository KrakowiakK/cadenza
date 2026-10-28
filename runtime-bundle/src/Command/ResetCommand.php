<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Command;

use Cadenza\Runtime\Draft\SiteManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('cadenza:reset', 'Start over. By default as a new version (history kept); --hard deletes the whole history.')]
final class ResetCommand
{
    public function __construct(private readonly SiteManager $site)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Site name for the blank canvas')] string $name = 'My site',
        #[Option('Delete every version and draft')] bool $hard = false,
    ): int {
        if (!$hard) {
            $version = $this->site->restore(1);
            $io->success(\sprintf('Back to the blank canvas as version %d; the history is kept.', $version->getNumber()));

            return 0;
        }
        if (!$io->confirm('Delete the whole history of the site?', false)) {
            return 1;
        }
        $this->site->wipe($name);
        $io->success('History deleted; the site is a blank canvas again (media files are kept).');

        return 0;
    }
}
