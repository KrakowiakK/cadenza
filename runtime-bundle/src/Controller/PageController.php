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
    ) {
    }

    #[Route('/{path}', name: 'cadenza_page', requirements: ['path' => '(?!_|assets/|build/|bundles/).*'], defaults: ['path' => ''], methods: ['GET', 'HEAD'], priority: -1000)]
    public function __invoke(Request $request, string $path): Response
    {
        $this->state->owner = $this->owner->isOwner($request);
        $document = $this->site->document();

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
        $this->state->document = $document;

        $page = Document::findPage($document, '/'.$path);
        if (null === $page) {
            $response = new Response($this->twig->render('@CadenzaRuntime/missing.html.twig', ['path' => Document::normalisePath($path)]), [] === $document['pages'] ? 200 : 404);
        } else {
            $response = new Response($this->twig->render('@CadenzaRuntime/page.html.twig', ['page' => $page]));
        }
        $response = $this->owner->remember($request, $response);
        if ($this->state->owner) {
            $response->headers->set('Cache-Control', 'no-store');
        }

        return $response;
    }
}
