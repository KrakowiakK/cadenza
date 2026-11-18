<?php

declare(strict_types=1);

namespace Cadenza\Runtime\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The application's MCP servers answer only the Cadenza harness (or another client holding the token). The "guest"
 * server has a token of its own, derived from the first: an agent the owner brings along can read the site and leave
 * sketches there, and that token opens neither the studio's servers nor publishing.
 */
final class McpGuardSubscriber
{
    public function __construct(
        #[Autowire('%cadenza.token%')]
        private readonly string $token,
    ) {
    }

    /**
     * The key of a sketch's private preview link: whoever proposed the sketch gets it back from draft_propose and can
     * look at the page as it would be, without being the owner. It opens that one sketch and nothing else.
     */
    public static function previewKey(string $token, int $draftId): string
    {
        return substr(hash_hmac('sha256', 'cadenza sketch preview '.$draftId, $token), 0, 32);
    }

    public static function guestToken(string $token): string
    {
        return 'czg_'.substr(hash_hmac('sha256', 'cadenza guest agent', $token), 0, 40);
    }

    #[AsEventListener(KernelEvents::REQUEST, priority: 250)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/_cadenza/mcp')) {
            return;
        }
        $given = $request->headers->get('X-Cadenza-Token') ?? preg_replace('/^Bearer\s+/i', '', (string) $request->headers->get('Authorization'));
        $guest = str_starts_with($request->getPathInfo(), '/_cadenza/mcp/guest');
        if (!hash_equals($this->token, (string) $given) && !($guest && hash_equals(self::guestToken($this->token), (string) $given))) {
            $event->setResponse(new JsonResponse(['error' => 'Invalid Cadenza token.'], 401));
        }
    }
}
