<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Owner;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides whether the visitor is the site owner (sees the chat panel and sketches).
 *
 * Owner mode "always" is for local development; "token" requires visiting once with ?cadenza_owner=<token>,
 * which stores a cookie. ?cadenza_guest=1 previews the site as a guest.
 */
final class OwnerResolver
{
    private const COOKIE = 'cadenza_owner';

    public function __construct(
        #[Autowire('%cadenza.owner_mode%')]
        private readonly string $mode,
        #[Autowire('%cadenza.token%')]
        private readonly string $token,
    ) {
    }

    public function isOwner(Request $request): bool
    {
        if ($request->query->getBoolean('cadenza_guest')) {
            return false;
        }
        if ('always' === $this->mode) {
            return true;
        }

        return hash_equals($this->token, (string) ($request->query->get('cadenza_owner') ?? $request->cookies->get(self::COOKIE, '')));
    }

    public function remember(Request $request, Response $response): Response
    {
        $given = $request->query->get('cadenza_owner');
        if (\is_string($given) && hash_equals($this->token, $given)) {
            $response->headers->setCookie(Cookie::create(self::COOKIE, $given, new \DateTimeImmutable('+1 year'), httpOnly: true, sameSite: Cookie::SAMESITE_LAX));
        }

        return $response;
    }
}
