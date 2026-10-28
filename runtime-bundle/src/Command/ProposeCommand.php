<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Command;

use Cadenza\Runtime\Draft\SiteManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('cadenza:propose', 'Create a draft from a JSON file with operations (the same language the agent uses).')]
final class ProposeCommand
{
    public function __construct(private readonly SiteManager $site)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('JSON file with a list of operations')] string $file,
        #[Argument('Summary of the change')] string $summary = 'Manual change',
        #[Option('Extend this draft')] ?int $draft = null,
        #[Option('Publish right away')] bool $publish = false,
    ): int {
        $operations = json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);
        $result = $this->site->propose($operations, $summary, $draft);
        if (!$result->isValid()) {
            $io->error($result->errors);

            return 1;
        }
        $id = (int) $result->draft->getId();
        $io->success(\sprintf('Draft %d: %s — open %s?cz_draft=%d', $id, json_encode($result->diff->summary()), $result->diff->focusPath($result->draft->getDocument()) ?? '/', $id));
        if ($publish) {
            $version = $this->site->publish($id);
            $io->success(\sprintf('Published as version %d.', $version->getNumber()));
        }

        return 0;
    }
}
