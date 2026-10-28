<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Stats;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * The background worker says "still here" every half a minute, so the dashboard can tell when nobody is processing
 * imports and form notifications.
 */
#[AsEventListener(WorkerRunningEvent::class)]
final class WorkerHeartbeat
{
    public const KEY = 'cadenza.worker_seen';

    private int $last = 0;

    public function __construct(private readonly CacheItemPoolInterface $cache)
    {
    }

    public function __invoke(WorkerRunningEvent $event): void
    {
        if (time() - $this->last < 30) {
            return;
        }
        $this->last = time();
        $this->cache->save($this->cache->getItem(self::KEY)->set($this->last));
    }

    public function lastSeen(): ?int
    {
        $item = $this->cache->getItem(self::KEY);

        return $item->isHit() ? (int) $item->get() : null;
    }
}
