<?php

declare(strict_types=1);

namespace Cadenza\Runtime\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The application's MCP servers answer only the Cadenza harness (or another client holding the token).
 */
final class McpGuardSubscriber
{
    public function __construct(
        #[Autowire('%cadenza.token%')]
        private readonly string $token,
    ) {
    }

    #[AsEventListener(KernelEvents::REQUEST, priority: 250)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/_cadenza/mcp')) {
            return;
        }
        $given = $request->headers->get('X-Cadenza-Token') ?? preg_replace('/^Bearer\s+/i', '', (string) $request->headers->get('Authorization'));
        if (!hash_equals($this->token, (string) $given)) {
            $event->setResponse(new JsonResponse(['error' => 'Invalid Cadenza token.'], 401));
        }
    }
}
