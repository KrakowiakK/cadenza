<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Controller;

use Cadenza\Runtime\Ask\SiteKnowledge;
use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Repository\SiteVersionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * How the published site introduces itself to machines: a sitemap and robots.txt for search engines, and llms.txt,
 * the site's content as plain text for AI assistants. All three follow the site by themselves; pages for invited
 * people are in none of them.
 */
#[AsController]
final class DiscoveryController
{
    public function __construct(
        private readonly SiteManager $site,
        private readonly SiteVersionRepository $versions,
        private readonly SiteKnowledge $knowledge,
    ) {
    }

    #[Route('/sitemap.xml', name: 'cadenza_sitemap', methods: ['GET'])]
    public function sitemap(Request $request): Response
    {
        $base = $request->getSchemeAndHttpHost();
        $updated = $this->versions->current()?->getPublishedAt()?->format('Y-m-d');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($this->publicPages() as $page) {
            $xml .= \sprintf("  <url><loc>%s</loc>%s</url>\n", htmlspecialchars($base.$page['path'], \ENT_XML1), null !== $updated ? '<lastmod>'.$updated.'</lastmod>' : '');
        }

        return new Response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    #[Route('/robots.txt', name: 'cadenza_robots', methods: ['GET'])]
    public function robots(Request $request): Response
    {
        return new Response(\sprintf("User-agent: *\nAllow: /\nDisallow: /_cadenza/\n\nSitemap: %s/sitemap.xml\n", $request->getSchemeAndHttpHost()), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    #[Route('/llms.txt', name: 'cadenza_llms', methods: ['GET'])]
    public function llms(Request $request): Response
    {
        $base = $request->getSchemeAndHttpHost();
        $pages = array_map(static fn (array $p): string => \sprintf('- [%s](%s)%s', $p['title'] ?? $p['path'], $base.$p['path'], ($p['description'] ?? null) ? ': '.$p['description'] : ''), $this->publicPages());
        $text = $this->knowledge->text();
        // llms.txt opens with the name, a summary and the pages; the content follows.
        [$head, $body] = array_pad(explode("\n", $text, 2), 2, '');

        return new Response($head."\n\n> ".\sprintf('The content of %s as plain text, generated from the published site.', $base)."\n\n## Pages\n".implode("\n", $pages)."\n".$body."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function publicPages(): array
    {
        return array_values(array_filter($this->site->document()['pages'] ?? [], static fn (array $p): bool => 'members' !== ($p['access'] ?? 'public')));
    }
}
