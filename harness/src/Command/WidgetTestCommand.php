<?php

declare(strict_types=1);

namespace App\Command;

use App\Studio\SiteClient;
use App\Studio\WidgetTester;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Tries a widget in a headless browser and shows what the model that can see thinks of it.
 */
#[AsCommand('cadenza:widget:test', 'Try a widget in a headless browser and review the screenshots')]
final class WidgetTestCommand
{
    public function __construct(
        private readonly SiteClient $site,
        private readonly WidgetTester $tester,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('the widget\'s name')] string $name): int
    {
        if (!$this->tester->available()) {
            $io->error('No Chrome or chromedriver (vendor/bin/bdi detect drivers).');

            return 1;
        }
        $widget = $this->site->widgetCode($name);
        if (!isset($widget['code'])) {
            $io->error('No widget "'.$name.'".');

            return 1;
        }
        $run = $this->tester->run($name, (int) $widget['version'], (string) ($widget['shape'] ?? 'wide'));
        $io->listing(array_merge($run['screenshots'], $run['errors'] ?: ['no browser errors']));
        $description = array_column($this->site->widgets(), 'description', 'name')[$name] ?? $name;
        $io->writeln($this->tester->review((string) $description, $run['screenshots'], $run['played']) ?? 'Looks right.');

        return 0;
    }
}
