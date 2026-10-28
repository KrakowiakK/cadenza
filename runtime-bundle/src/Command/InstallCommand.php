<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Command;

use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Repository\SiteVersionRepository;
use Cadenza\Runtime\Site\Document;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('cadenza:init', 'Start the site as a blank canvas (first version).')]
final class InstallCommand
{
    public function __construct(
        private readonly SiteManager $site,
        private readonly SiteVersionRepository $versions,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('Site name')] string $name = 'My site'): int
    {
        if (null !== $this->versions->current()) {
            $io->note('The site already exists; nothing to do.');

            return 0;
        }
        $this->site->initialise(Document::empty($name), 'Blank canvas');
        $io->success(\sprintf('Site "%s" created. Open it and describe what you want in the Cadenza bar.', $name));

        return 0;
    }
}
