<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Stats;

use Cadenza\Runtime\Owner\OwnerResolver;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Counts visitors' page views after the response is sent. The owner, bots, link previews, prefetches and embedded
 * thumbnails do not count.
 */
#[AsEventListener(KernelEvents::TERMINATE)]
final class VisitCounter
{
    private const BOTS = '~bot|crawl|spider|slurp|preview|facebookexternalhit|embedly|curl|wget|python|headless|lighthouse~i';

    public function __construct(
        private readonly Connection $db,
        private readonly OwnerResolver $owner,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        if ('cadenza_page' !== $request->attributes->get('_route') || !$request->isMethod('GET') || 200 !== $event->getResponse()->getStatusCode()
            || $this->owner->isOwner($request) || $request->query->has('cz_embed')
            || preg_match(self::BOTS, (string) $request->headers->get('User-Agent'))
            || \in_array('prefetch', [$request->headers->get('Sec-Purpose'), $request->headers->get('X-Sec-Purpose'), $request->headers->get('Purpose')], true)) {
            return;
        }
        $path = mb_substr('/'.ltrim((string) $request->attributes->get('path', ''), '/'), 0, 191);
        $day = date('Y-m-d');
        try {
            if (0 === $this->db->executeStatement('UPDATE cadenza_page_view SET views = views + 1 WHERE day = ? AND path = ?', [$day, $path])) {
                $this->db->executeStatement('INSERT INTO cadenza_page_view (day, path, views) VALUES (?, ?, 1)', [$day, $path]);
            }
        } catch (\Throwable $e) {
            // Two first visits of the same page at once: the second insert loses, one visit uncounted is fine.
            $this->logger?->info('Visit not counted: {message}', ['message' => $e->getMessage()]);
        }
    }
}
