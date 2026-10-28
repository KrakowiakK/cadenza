<?php

declare(strict_types=1);

namespace App\Studio;

use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Panther\Client;

/**
 * Looks at a live page the way a visitor with a phone sees it: a headless Chrome at phone size measures what sticks
 * out of the screen, text too small to read and buttons too small for a thumb, and a model that can see looks at
 * the screens one by one.
 */
final class PhoneCheck
{
    private const WIDTH = 390;
    private const HEIGHT = 844;
    private const SCREENS = 5;

    /** Measured in the page: what a phone visitor would struggle with. */
    private const MEASURE = <<<'JS'
        const vw = document.documentElement.clientWidth, out = [], small = [], tiny = [];
        const label = (el) => (el.closest('[data-node-type]')?.getAttribute('data-node-type') || el.tagName.toLowerCase())
            + (el.textContent.trim() ? ' "' + el.textContent.trim().replace(/\s+/g, ' ').slice(0, 40) + '"' : '');
        for (const el of document.querySelectorAll('body *')) {
            if (el.closest('#cz-panel, [data-cz-ui], script, style, svg *')) continue;
            const r = el.getBoundingClientRect(), cs = getComputedStyle(el);
            if (r.width === 0 || r.height === 0 || cs.visibility === 'hidden') continue;
            if (r.right > vw + 2 && cs.position !== 'fixed' && !el.closest('[style*="overflow"], .overflow-x-auto, .overflow-hidden')) out.push(label(el));
            const own = [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim().length > 2);
            if (own && parseFloat(cs.fontSize) < 12) small.push(label(el));
            // A radio or a checkbox is tapped with its label; a link inside a sentence is part of the text.
            const target = el.matches('a[href], button, select, textarea, input:not([type=radio]):not([type=checkbox]):not([type=hidden])') && !el.closest('p, li, label');
            if (target && (r.height < 32 || r.width < 32) && el.offsetParent) tiny.push(label(el));
        }
        const uniq = (a) => [...new Set(a)].slice(0, 6);
        const nav = performance.getEntriesByType('navigation')[0];
        const files = performance.getEntriesByType('resource').map((r) => ({name: r.name.replace(location.origin, '').split('?')[0], kb: Math.round((r.transferSize || r.encodedBodySize || 0) / 1024)}));
        const heavy = files.filter((f) => f.kb > 0).sort((a, b) => b.kb - a.kb).slice(0, 4).map((f) => f.name + ' ' + f.kb + ' KB');
        return {wide: document.documentElement.scrollWidth > vw + 2, height: document.documentElement.scrollHeight, out: uniq(out), small: uniq(small), tiny: uniq(tiny),
            loadMs: nav ? Math.round(nav.loadEventEnd || nav.domComplete) : null, totalKb: files.reduce((t, f) => t + f.kb, 0) + Math.round((nav?.transferSize || 0) / 1024), heavy};
        JS;

    public function __construct(
        #[Autowire('%env(CADENZA_APP_URL)%')]
        private readonly string $appUrl,
        private readonly WidgetTester $tester,
        #[Autowire(service: 'ai.platform.generic.vision')]
        private readonly PlatformInterface $vision,
        #[Autowire('%env(CADENZA_LLM_VISION_MODEL)%')]
        private readonly string $visionModel,
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
        return $this->tester->available();
    }

    /**
     * @return array{measured: array<string, mixed>, look: ?string, screens: int}
     */
    public function check(string $path, string $language): array
    {
        $client = Client::createChromeClient($this->chromeDriver, [
            '--headless=new', '--window-size='.self::WIDTH.','.self::HEIGHT, '--hide-scrollbars', '--mute-audio', '--force-device-scale-factor=1',
            '--user-agent=Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
        ], ['port' => random_int(9600, 9899)]);
        $dir = sys_get_temp_dir().'/cadenza-phone-check';
        is_dir($dir) || mkdir($dir, 0o775, true);
        $screens = [];
        try {
            $client->request('GET', rtrim($this->appUrl, '/').'/'.ltrim($path, '/'));
            usleep(1_500_000);
            $driver = $client->getWebDriver();
            $measured = (array) $driver->executeScript(self::MEASURE);
            $height = (int) ($measured['height'] ?? self::HEIGHT);
            for ($i = 0, $y = 0; $i < self::SCREENS && $y < $height; ++$i, $y += self::HEIGHT - 80) {
                $driver->executeScript(\sprintf('window.scrollTo(0, %d)', $y));
                usleep(700_000);
                $client->takeScreenshot($screens[] = \sprintf('%s/%s-%d.png', $dir, md5($path), $i));
            }
        } finally {
            $client->quit();
        }

        return ['measured' => $measured, 'look' => $this->look($screens, $language), 'screens' => \count($screens)];
    }

    /**
     * @param list<string> $screens
     */
    private function look(array $screens, string $language): ?string
    {
        [$platform, $model] = '' !== $this->reviewModel ? [$this->main, $this->reviewModel] : [$this->vision, $this->visionModel];
        if ([] === $screens || '' === $model) {
            return null;
        }
        $parts = [\sprintf('These are screenshots of one web page on a phone (390 px wide), from the top down, screen by screen. Look like a picky visitor: text that is hard to read (too small, low contrast on its background or photo), things cut off or sticking out at the side, overlapping elements, cramped or empty-looking sections, images squashed or cropped badly, buttons hard to tap. Ignore taste and content. Answer in the language with the code "%s", at most 5 short points, each naming the section; if it all reads well, answer exactly "OK".', $language)];
        foreach ($screens as $file) {
            $parts[] = Image::fromFile($file);
        }
        try {
            $answer = trim($platform->invoke($model, new MessageBag(Message::ofUser(...$parts)), str_contains($model, '/') ? ['reasoning' => ['enabled' => false]] : [])->asText());
        } catch (\Throwable $e) {
            $this->logger?->warning('Phone check failed: {message}', ['message' => $e->getMessage()]);

            return null;
        }

        return '' === $answer ? null : $answer;
    }
}
