<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Block;

use League\CommonMark\CommonMarkConverter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\SecurityPolicy;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Renders the agent's own block templates — the riskiest thing on the site, so in three walls:
 *  1. a separate Twig environment: no globals, no app, no access to the site's services or secrets;
 *  2. the Twig sandbox: only the tags, filters and functions below, no methods or properties of objects;
 *  3. the HTML sanitiser on the output: no scripts, event handlers, styles, frames or foreign images.
 * Icons are rendered after sanitising, from a placeholder the sandbox can only produce for known icon names.
 */
final class BlockRenderer
{
    public const TAGS = ['if', 'for', 'set', 'apply'];
    public const FILTERS = ['escape', 'e', 'upper', 'lower', 'capitalize', 'title', 'trim', 'default', 'length', 'slice', 'join', 'split', 'first', 'last',
        'nl2br', 'date', 'format_date', 'number_format', 'round', 'abs', 'replace', 'striptags', 'keys', 'merge', 'sort', 'reverse', 'batch', 'format',
        'markdown', 'url_encode', 'column', 'filter', 'map', 'reduce'];
    public const FUNCTIONS = ['range', 'max', 'min', 'icon', 'date', 'cycle'];

    private ?Environment $twig = null;
    private ?HtmlSanitizer $sanitizer = null;

    public function __construct(
        #[Autowire('%kernel.cache_dir%/cadenza_blocks')]
        private readonly string $cacheDir,
    ) {
    }

    /**
     * @param array<string, mixed> $props
     */
    public function render(string $name, int $version, string $template, array $props, ?\Closure $icon = null): string
    {
        $twig = $this->twig();
        $key = \sprintf('block_%s_v%d_%s', $name, $version, substr(hash('xxh128', $template), 0, 12));
        $twig->getLoader()->setTemplate($key, $template);
        $html = $this->sanitizer()->sanitize($twig->render($key, ['props' => $props]));

        // Icons: the only markup added after sanitising, from names the template could not forge into anything else.
        return (string) preg_replace_callback('/\[\[cz-icon:([a-z0-9-]{1,40})\|([a-z0-9 :\/.\[\]-]{0,80})\]\]/', static fn (array $m): string => null !== $icon ? $icon($m[1], $m[2] ?: 'size-5') : '', $html);
    }

    /**
     * Compiles and renders a template with sample props: the errors the agent should fix before the block exists.
     *
     * @param array<string, mixed> $sample
     *
     * @return list<string>
     */
    public function check(string $template, array $sample): array
    {
        if (preg_match('/<\s*(script|style|iframe|object|embed|link|meta|form)\b|\bon[a-z]+\s*=|javascript:/i', $template, $m)) {
            return [\sprintf('templates cannot contain %s (no scripts, styles, frames, forms or event handlers; use the built-in "form" pattern for forms).', trim($m[0]))];
        }
        try {
            $this->render('check', random_int(1, \PHP_INT_MAX), $template, $sample);
        } catch (\Twig\Sandbox\SecurityError $e) {
            return [$e->getMessage().' Allowed tags: '.implode(', ', self::TAGS).'; filters: '.implode(', ', self::FILTERS).'; functions: '.implode(', ', self::FUNCTIONS).'.'];
        } catch (\Throwable $e) {
            return [$e->getMessage()];
        }

        return [];
    }

    private function twig(): Environment
    {
        if (null !== $this->twig) {
            return $this->twig;
        }
        $twig = new Environment(new ArrayLoader(), ['autoescape' => 'html', 'strict_variables' => false, 'cache' => new FilesystemCache($this->cacheDir)]);
        $twig->addExtension(new SandboxExtension(new SecurityPolicy(self::TAGS, self::FILTERS, [], [], self::FUNCTIONS), true));
        if (class_exists(IntlExtension::class)) {
            $twig->addExtension(new IntlExtension());
        }
        $markdown = new CommonMarkConverter(['html_input' => 'escape', 'allow_unsafe_links' => false]);
        $twig->addFilter(new TwigFilter('markdown', static fn (?string $text): string => null === $text ? '' : (string) $markdown->convert($text), ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('icon', static fn (string $name, string $class = 'size-5'): string => \sprintf('[[cz-icon:%s|%s]]', preg_replace('/[^a-z0-9-]/', '', strtolower($name)), preg_replace('/[^a-z0-9 :\/.\[\]-]/', '', $class))));

        return $this->twig = $twig;
    }

    private function sanitizer(): HtmlSanitizer
    {
        return $this->sanitizer ??= new HtmlSanitizer((new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowAttribute('class', '*')
            ->allowAttribute('id', '*')
            ->allowAttribute('lang', '*')
            ->allowAttribute('open', 'details')
            ->allowElement('details', ['open', 'class'])
            ->allowElement('summary', ['class'])
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowMediaSchemes([])
            ->withMaxInputLength(200_000));
    }
}
