<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Phone;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Short-lived links the owner opens on their phone (via a QR code) to send photos straight from the camera roll.
 */
final class PhoneSessions
{
    private const TTL = 3600;

    public function __construct(private readonly CacheItemPoolInterface $cacheApp)
    {
    }

    /**
     * @return array{token: string, status: string, media: list<int>, albums: array<string, int>, createdAt: int, locale: string}
     */
    public function create(string $locale): array
    {
        $session = ['token' => bin2hex(random_bytes(16)), 'status' => 'waiting', 'media' => [], 'albums' => [], 'createdAt' => time(), 'locale' => $locale];
        $this->save($session);

        return $session;
    }

    /**
     * @return array{token: string, status: string, media: list<int>, albums: array<string, int>, createdAt: int, locale: string}|null
     */
    public function get(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $item = $this->cacheApp->getItem('cz_phone_'.$token);

        return $item->isHit() ? $item->get() : null;
    }

    /**
     * @param array<string, mixed> $session
     */
    public function save(array $session): void
    {
        $item = $this->cacheApp->getItem('cz_phone_'.$session['token']);
        $item->set($session)->expiresAfter(self::TTL);
        $this->cacheApp->save($item);
    }
}
