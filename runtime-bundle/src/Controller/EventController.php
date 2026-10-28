<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Controller;

use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Site\Document;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * "Add to calendar" for a countdown: the event as an iCalendar file any calendar app opens.
 */
#[AsController]
final class EventController
{
    public function __construct(private readonly SiteManager $site)
    {
    }

    #[Route('/_cadenza/event/{id}.ics', name: 'cadenza_event_ics', requirements: ['id' => '[a-z][a-z0-9_-]{2,24}'], methods: ['GET'])]
    public function __invoke(Request $request, string $id): Response
    {
        $doc = $this->site->document();
        $node = Document::findNode($doc, $id);
        if (null === $node || 'countdown' !== $node['type'] || false === strtotime((string) ($node['props']['date'] ?? ''))) {
            throw new NotFoundHttpException();
        }

        return new Response(self::ics($node['props'], $doc['name'] ?? '', $id.'@'.$request->getHost(), $request->getSchemeAndHttpHost()), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="event.ics"',
        ]);
    }

    /**
     * @param array<string, mixed> $props
     */
    public static function ics(array $props, string $siteName, string $uid, string $url): string
    {
        $date = (string) $props['date'];
        $time = \is_string($props['time'] ?? null) && preg_match('/^\d{1,2}:\d{2}$/', $props['time']) ? $props['time'] : null;
        $end = \is_string($props['endTime'] ?? null) && preg_match('/^\d{1,2}:\d{2}$/', $props['endTime']) ? $props['endTime'] : null;
        $escape = static fn (string $s): string => str_replace(["\\", ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $s);
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Cadenza//EN', 'BEGIN:VEVENT', 'UID:'.$uid, 'DTSTAMP:'.gmdate('Ymd\THis\Z')];
        if (null === $time) {
            $lines[] = 'DTSTART;VALUE=DATE:'.date('Ymd', (int) strtotime($date));
            $lines[] = 'DTEND;VALUE=DATE:'.date('Ymd', (int) strtotime($date.' +1 day'));
        } else {
            // Floating local time: the event happens at this hour wherever the calendar is.
            $lines[] = 'DTSTART:'.date('Ymd\THis', (int) strtotime($date.' '.$time));
            $lines[] = 'DTEND:'.date('Ymd\THis', (int) strtotime($date.' '.($end ?? $time).(null === $end ? ' +2 hours' : '')));
        }
        $lines[] = 'SUMMARY:'.$escape((string) (($props['title'] ?? null) ?: $siteName));
        if (\is_string($props['place'] ?? null) && '' !== $props['place']) {
            $lines[] = 'LOCATION:'.$escape($props['place']);
        }
        $lines[] = 'URL:'.$url;
        array_push($lines, 'END:VEVENT', 'END:VCALENDAR');

        return implode("\r\n", $lines)."\r\n";
    }
}
