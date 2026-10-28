<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Controller;

use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Owner\OwnerResolver;
use Cadenza\Runtime\Stats\SiteStats;
use Cadenza\Runtime\Twig\RenderState;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

/**
 * The owner's overview of their site: visitors, answers, changes, content and the application's health.
 */
#[AsController]
final class DashboardController
{
    public function __construct(
        private readonly SiteStats $stats,
        private readonly SiteManager $site,
        private readonly OwnerResolver $owner,
        private readonly RenderState $state,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/_cadenza/dashboard', name: 'cadenza_dashboard', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        if (!$this->owner->isOwner($request)) {
            // Visitors should not even learn that the page exists.
            throw new NotFoundHttpException();
        }
        $this->state->owner = true;
        $this->state->document = $this->site->document();

        $response = new Response($this->twig->render('@CadenzaRuntime/dashboard.html.twig', [
            'page' => null,
            'visits' => $this->stats->visits(30),
            'content' => $this->stats->content(),
            'forms' => $this->stats->forms(),
            'health' => $this->stats->health(),
        ]));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
