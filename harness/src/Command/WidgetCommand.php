<?php

declare(strict_types=1);

namespace App\Command;

use App\Studio\SiteClient;
use App\Studio\WidgetProgrammer;
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
        private readonly SiteClient $site,
        #[Autowire(service: 'ai.platform.generic.default')]
        private readonly PlatformInterface $platform,
        #[Autowire('%env(default:cadenza.llm_model:CADENZA_CODE_MODEL)%')]
        private readonly string $model,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('lowercase name, e.g. "frogger"')] string $name,
        #[Argument('what it should be and do')] string $brief,
        #[Option('wide, square, tall, banner, auto or background')] string $shape = 'wide',
        #[Option('another model of the same API, e.g. "kimi-k2.7-code"')] ?string $model = null,
        #[Option('language of its texts')] string $language = 'pl',
    ): int {
        $started = microtime(true);
        $programmer = new WidgetProgrammer($this->platform, $model ?? $this->model);
        $code = $programmer->write($brief, $language, $this->site->widgetLibraries(), $shape, null, null, static fn (string $step) => $io->comment($step));
        $result = $this->site->defineWidget($name, ucfirst(str_replace('-', ' ', $name)), mb_substr($brief, 0, 500), $code, $shape);
        if (!($result['ok'] ?? false)) {
            $io->error((array) ($result['errors'] ?? ['not saved']));

            return 1;
        }
        $errors = WidgetProgrammer::syntaxErrors($code);
        $io->success(\sprintf('%s v%d: %d KB in %d s%s', $name, $result['version'], \strlen($code) >> 10, microtime(true) - $started, [] !== $errors ? ' — syntax: '.implode('; ', $errors) : ''));

        return 0;
    }
}
