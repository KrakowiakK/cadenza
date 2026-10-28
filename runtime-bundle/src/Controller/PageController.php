<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Controller;

use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Owner\OwnerResolver;
use Cadenza\Runtime\Repository\SiteVersionRepository;
use Cadenza\Runtime\Site\Diff;
use Cadenza\Runtime\Site\Document;
use Cadenza\Runtime\Twig\RenderState;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * Renders any page of the site from the page tree — the live version, or a draft as a sketch (?cz_draft=ID).
 */
#[AsController]
final class PageController
{
    public function __construct(
        private readonly SiteManager $site,
        private readonly SiteVersionRepository $versions,
        private readonly OwnerResolver $owner,
        private readonly RenderState $state,
        private readonly Environment $twig,
        private readonly \Cadenza\Runtime\Collection\Collections $collections,
        private readonly \Cadenza\Runtime\Account\Accounts $accounts,
    ) {
    }

    #[Route('/{path}', name: 'cadenza_page', requirements: ['path' => '(?!_|assets/|build/|bundles/).*'], defaults: ['path' => ''], methods: ['GET', 'HEAD'], priority: -1000)]
    public function __invoke(Request $request, string $path): Response
    {
        $this->state->owner = $this->owner->isOwner($request);
        $document = $this->site->document();
        // First run: a blank site without an owner — whoever started it creates the owner account first.
        if ([] === $document['pages'] && !$this->state->owner && !$this->accounts->hasOwner()) {
            return new \Symfony\Component\HttpFoundation\RedirectResponse('/_cadenza/setup');
        }

        $draftId = $request->query->getInt('cz_draft');
        if ($this->state->owner && $draftId > 0 && null !== $draft = $this->versions->draft($draftId)) {
            $sketch = $this->site->rebased($draft);
            $this->state->diff = Diff::between($document, $sketch);
            $this->state->draftId = $draftId;
            $this->state->draftSummary = $draft->getSummary();
            $this->state->showBefore = 'before' === $request->query->get('cz_view');
            if (!$this->state->showBefore) {
                $document = $sketch;
            }
        }
        $versionNumber = $request->query->getInt('cz_version');
        if ($this->state->owner && null === $this->state->diff && $versionNumber > 0 && null !== $version = $this->versions->byNumber($versionNumber)) {
            $this->state->previewVersion = $version;
            $this->state->currentNumber = $this->site->currentNumber();
            $document = $version->getDocument();
        }
        $this->state->document = $document;
        $this->state->embed = $request->query->getBoolean('cz_embed');

        $page = Document::findPage($document, '/'.$path);
        // /{collection}/{record}: every record of a collection has its own page.
        if (null === $page && preg_match('~^([a-z0-9-]+)/([a-z0-9-]+)$~', $path, $m) && null !== ($collection = $this->collections->get($m[1])) && null !== ($record = $this->collections->record($collection, $m[2]))) {
            return new Response($this->twig->render('@CadenzaRuntime/record.html.twig', ['collection' => $collection, 'record' => $record, 'page' => null]));
        }
        if (null === $page) {
            $response = new Response($this->twig->render('@CadenzaRuntime/missing.html.twig', ['path' => Document::normalisePath($path)]), [] === $document['pages'] ? 200 : 404);
        } elseif ('members' === ($page['access'] ?? 'public') && !$this->owner->isMember($request)) {
            // Only invited people: the page stays closed and tells nobody what is behind it.
            $response = new Response($this->twig->render('@CadenzaRuntime/members.html.twig', ['page' => null, 'path' => $page['path'], 'title' => $page['title']]), 403);
            $response->headers->set('X-Robots-Tag', 'noindex');
        } else {
            $response = new Response($this->twig->render('@CadenzaRuntime/page.html.twig', ['page' => $page]));
        }
        if ('members' === ($page['access'] ?? 'public')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }
        if ($this->state->owner) {
            $response->headers->set('Cache-Control', 'no-store');
        }

        return $response;
    }
}
