<?php

declare(strict_types=1);

namespace App\Studio;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Panther\Client;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Tries a widget the way a visitor would, before the owner sees it: opens it in a headless Chrome (Symfony Panther),
 * starts it and presses a few keys, collects the browser's errors and takes screenshots — then a model that can see
 * looks at them and says what is wrong. Skipped when the computer has no Chrome.
 */
final class WidgetTester
{
    /** The frame of each shape, as a computer shows it. */
    private const SIZES = ['wide' => [1100, 620], 'square' => [640, 640], 'tall' => [450, 600], 'banner' => [1150, 380], 'auto' => [1100, 700], 'background' => [1400, 700]];

    public function __construct(
        #[Autowire('%env(CADENZA_APP_URL)%')]
        private readonly string $appUrl,
        #[Autowire(service: 'ai.platform.generic.vision')]
        private readonly PlatformInterface $vision,
        #[Autowire('%env(CADENZA_LLM_VISION_MODEL)%')]
        private readonly string $visionModel,
        // A model of the main API that can see (e.g. "gemma4:31b" on Ollama Cloud), when the vision API is rate-limited.
        #[Autowire(service: 'ai.platform.generic.default')]
        private readonly PlatformInterface $main,
        #[Autowire('%env(CADENZA_REVIEW_MODEL)%')]
        private readonly string $reviewModel,
        #[Autowire('%kernel.project_dir%/drivers/chromedriver')]
        private readonly string $chromeDriver,
        private readonly ?\Psr\Log\LoggerInterface $logger = null,
    ) {
    }

    public function available(): bool
    {
        return is_file($this->chromeDriver) && null !== self::chrome();
    }

    /**
     * @return array{errors: list<string>, screenshots: list<string>, played: bool}
     */
    public function run(string $name, int $version, string $shape): array
    {
        [$width, $height] = self::SIZES[$shape] ?? self::SIZES['wide'];
        $client = Client::createChromeClient($this->chromeDriver, [
            '--headless=new', '--window-size='.$width.','.$height, '--hide-scrollbars', '--mute-audio',
            '--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--autoplay-policy=no-user-gesture-required',
        ], ['port' => random_int(9600, 9899), 'capabilities' => ['goog:loggingPrefs' => ['browser' => 'ALL']]]);
        $dir = sys_get_temp_dir().'/cadenza-widget-test';
        is_dir($dir) || mkdir($dir, 0o775, true);
        $screenshots = [];
        $played = false;
        try {
            $client->request('GET', rtrim($this->appUrl, '/').'/_cadenza/widget/'.$name.'?v='.$version.'&test='.time());
            usleep(1_500_000);
            $client->takeScreenshot($screenshots[] = $dir.'/'.$name.'-1.png');
            $driver = $client->getWebDriver();
            $code = (string) $driver->executeScript('return document.body.innerHTML');
            if (preg_match('/keydown|keyup|touchstart|pointerdown|click/i', $code)) {
                // Like a visitor: press the start button (or the middle), then play a little.
                $played = true;
                $buttons = $driver->findElements(WebDriverBy::xpath('//button[not(@disabled)]'));
                $start = null;
                foreach ($buttons as $button) {
                    if ($button->isDisplayed() && preg_match('/start|graj|zagraj|play|rozpocznij|begin|go\b|spróbuj|try/i', $button->getText())) {
                        $start = $button;
                        break;
                    }
                }
                try {
                    null !== $start ? $start->click() : $driver->findElement(WebDriverBy::tagName('body'))->click();
                } catch (\Throwable) {
                    // covered or not clickable: the keys below still try
                }
                $keyboard = $driver->getKeyboard();
                $keyboard->sendKeys(WebDriverKeys::SPACE);
                usleep(400_000);
                foreach ([WebDriverKeys::ARROW_UP, WebDriverKeys::ARROW_UP, WebDriverKeys::ARROW_LEFT, WebDriverKeys::ARROW_UP, WebDriverKeys::ARROW_RIGHT] as $key) {
                    $keyboard->sendKeys($key);
                    usleep(350_000);
                }
                usleep(600_000);
                $client->takeScreenshot($screenshots[] = $dir.'/'.$name.'-2.png');
            } else {
                usleep(2_000_000);
                $client->takeScreenshot($screenshots[] = $dir.'/'.$name.'-2.png');
            }
            $errors = [];
            foreach ($driver->manage()->getLog('browser') as $entry) {
                if ('SEVERE' === ($entry['level'] ?? '') && !str_contains($entry['message'], 'favicon')) {
                    // "http://…/_cadenza/widget/x?v=2 41:12 Uncaught TypeError: …" → "line 41: Uncaught TypeError: …"
                    $errors[] = (string) preg_replace('~^\S+ (\d+)(?::\d+)? ~', 'line $1 of the page: ', $entry['message']);
                }
            }
        } catch (\Throwable $e) {
            $errors = ['The test browser failed: '.$e->getMessage()];
        } finally {
            $client->quit();
        }

        return ['errors' => array_values(array_unique($errors)), 'screenshots' => $screenshots, 'played' => $played];
    }

    /**
     * What a model that can see thinks is wrong in the screenshots; null when it looks right (or cannot tell).
     *
     * @param list<string> $screenshots
     */
    public function review(string $brief, array $screenshots, bool $played, string $shape = 'wide', string $language = 'pl'): ?string
    {
        [$platform, $model] = '' !== $this->reviewModel ? [$this->main, $this->reviewModel] : [$this->vision, $this->visionModel];
        if ([] === $screenshots || '' === $model) {
            return null;
        }
        $parts = [\sprintf("These are screenshots of a widget on a website: first right after loading, then %s.\nWhat it should be: %s\n\nLook carefully. List concrete problems a visitor would see: an empty or blank area where the content should be, things drawn outside their board or cut off, overlapping text or buttons, unreadable text, a game that did not start or did not react, a layout that does not fill its frame, text in a language other than the rest. A game over after these few random keys is normal, not a problem. Ignore taste. Answer with JSON only: {\"ok\": true} if it works and looks right, else {\"ok\": false, \"problems\": [\"…\", \"…\"]}.",
            $played ? 'after pressing Start (or clicking the middle), Space, and arrow keys for two seconds' : 'two seconds later', $brief)
            .\sprintf("\nEvery text a visitor reads must be in the language with the code \"%s\"; any text in another language is a problem.", $language)
            .('background' === $shape ? "\nIt is a decorative background: the page shows its own title over it, so no text is expected here; sparse, soft shapes and empty space are intended." : '')];
        foreach ($screenshots as $file) {
            $parts[] = Image::fromFile($file);
        }
        try {
            $answer = $platform->invoke($model, new MessageBag(Message::ofUser(...$parts)), str_contains($model, '/') ? ['reasoning' => ['enabled' => false]] : [])->asText();
        } catch (\Throwable $e) {
            $this->logger?->warning('Widget review failed: {message}', ['message' => $e->getMessage()]);

            return null;
        }
        $this->logger?->info('Widget review: {answer}', ['answer' => $answer]);
        $verdict = \App\Agent\JsonRepair::decode($answer);
        if (!\is_array($verdict) || ($verdict['ok'] ?? true) || [] === ($verdict['problems'] ?? [])) {
            return null;
        }

        return implode("\n", array_map(static fn (mixed $p): string => '- '.(\is_string($p) ? $p : json_encode($p)), (array) $verdict['problems']));
    }

    private static function chrome(): ?string
    {
        foreach (['/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', '/Applications/Chromium.app/Contents/MacOS/Chromium'] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return (new ExecutableFinder())->find('google-chrome') ?? (new ExecutableFinder())->find('chromium') ?? (new ExecutableFinder())->find('chromium-browser');
    }
}
