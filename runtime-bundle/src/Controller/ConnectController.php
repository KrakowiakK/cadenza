<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Controller;

use Cadenza\Runtime\Connector\Connectors;
use Cadenza\Runtime\Owner\OwnerResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sign-in with an outside service, in a popup opened from the studio panel. The token stays in the application.
 */
#[AsController]
final class ConnectController
{
    public function __construct(
        private readonly Connectors $connectors,
        private readonly OwnerResolver $owner,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    #[Route('/_cadenza/connect/{connector}', name: 'cadenza_connect', methods: ['GET'])]
    public function start(Request $request, string $connector): Response
    {
        if (!$this->owner->isOwner($request)) {
            return new Response('Only the owner can connect accounts.', 403);
        }
        $service = $this->connectors->get($connector);
        if (!$service->oauthAvailable()) {
            return $this->finish($connector, false, 'Sign-in is not configured for '.$service->label().'. Upload an export file instead.');
        }
        $state = bin2hex(random_bytes(16));
        $request->getSession()->set('cadenza_connect_state', $state);

        return new RedirectResponse($service->authorizationUrl($this->redirectUri($connector), $state));
    }

    #[Route('/_cadenza/connect/{connector}/callback', name: 'cadenza_connect_callback', methods: ['GET'])]
    public function callback(Request $request, string $connector): Response
    {
        if (!$this->owner->isOwner($request)) {
            return new Response('Only the owner can connect accounts.', 403);
        }
        $expected = $request->getSession()->remove('cadenza_connect_state');
        if (!\is_string($expected) || !hash_equals($expected, (string) $request->query->get('state'))) {
            return $this->finish($connector, false, 'The sign-in expired, try again.');
        }
        if ($request->query->has('error')) {
            return $this->finish($connector, false, (string) ($request->query->get('error_description') ?? $request->query->get('error')));
        }
        try {
            $auth = $this->connectors->get($connector)->finishAuthorization((string) $request->query->get('code'), $this->redirectUri($connector));
            $connection = $this->connectors->connect($connector, $auth);
        } catch (\Throwable $e) {
            return $this->finish($connector, false, $e->getMessage());
        }

        return $this->finish($connector, true, $connection->getAccount());
    }

    private function redirectUri(string $connector): string
    {
        return $this->urls->generate('cadenza_connect_callback', ['connector' => $connector], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * A tiny page that tells the panel (window.opener) how it went and closes the popup.
     */
    private function finish(string $connector, bool $ok, string $detail): Response
    {
        $payload = json_encode(['type' => 'cadenza:connected', 'connector' => $connector, 'ok' => $ok, 'detail' => $detail], \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT);

        return new Response(\sprintf('<!doctype html><meta charset="utf-8"><title>Cadenza</title><body style="font:15px system-ui;padding:2rem">%s<script>try{window.opener&&window.opener.postMessage(%s, location.origin)}catch(e){};setTimeout(()=>window.close(),%d)</script>', htmlspecialchars($ok ? '✓ Connected: '.$detail : '✗ '.$detail), $payload, $ok ? 600 : 4000));
    }
}
