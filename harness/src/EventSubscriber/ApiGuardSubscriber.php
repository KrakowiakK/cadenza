<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The panel runs on the user's application (another origin) and calls /api with the shared Cadenza token.
 */
final class ApiGuardSubscriber
{
    public function __construct(
        #[Autowire('%env(CADENZA_APP_URL)%')]
        private readonly string $appUrl,
        #[Autowire('%env(CADENZA_TOKEN)%')]
        private readonly string $token,
    ) {
    }

    #[AsEventListener(KernelEvents::REQUEST, priority: 250)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }
        if ('OPTIONS' === $request->getMethod()) {
            $event->setResponse(new Response('', 204));

            return;
        }
        if (!hash_equals($this->token, (string) $request->headers->get('X-Cadenza-Token'))) {
            $event->setResponse(new JsonResponse(['error' => 'Invalid Cadenza token.'], 401));
        }
    }

    #[AsEventListener(KernelEvents::RESPONSE)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }
        $origin = $event->getRequest()->headers->get('Origin');
        $allowed = rtrim($this->appUrl, '/');
        $alias = str_replace('127.0.0.1', 'localhost', $allowed);
        if (\in_array($origin, [$allowed, $alias, str_replace('localhost', '127.0.0.1', $allowed)], true)) {
            $headers = $event->getResponse()->headers;
            $headers->set('Access-Control-Allow-Origin', $origin);
            $headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Cadenza-Token, Accept');
            $headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $headers->set('Vary', 'Origin');
        }
    }
}
