<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Studio\SiteClient;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('site_overview', 'The owner\'s dashboard as numbers: visits (this week, the week before, per day, most visited pages), content, recent changes and whether the application is healthy. Use it when the owner asks how the site is doing, how many people visited, what changed lately, or whether everything works.', method: 'read')]
final class StatsTools
{
    public function __construct(private readonly SiteClient $site)
    {
    }

    public function read(): string
    {
        $s = $this->site->stats();
        if (!isset($s['visits'])) {
            return 'Cannot read the statistics right now.';
        }
        $v = $s['visits'];
        $c = $s['content'];
        $h = $s['health'];
        $out = [
            \sprintf('Visits: %d today, %d this week, %d the week before, %d in 30 days (visitors only, without cookies; the owner and bots are not counted).', $v['today'], $v['week'], $v['previousWeek'], $v['total']),
            'Days with visits: '.(implode(', ', array_map(static fn (array $d): string => $d['day'].' '.$d['views'], $v['days'])) ?: 'none'),
            'Most visited: '.(implode(', ', array_map(static fn (array $p): string => $p['path'].' '.$p['views'], $v['pages'])) ?: '—'),
            \sprintf('Content: %d pages, %d blocks, %d photos in %d albums; collections: %s.', $c['pages'], $c['blocks'], $c['media'], $c['albums'], implode(', ', array_map(static fn (array $x): string => $x['name'].' ('.$x['records'].')', $c['collections'])) ?: 'none'),
            'Recent versions: '.implode('; ', array_map(static fn (array $x): string => '#'.$x['number'].' '.$x['summary'], \array_slice($c['history'], 0, 4))),
            \sprintf('Health: %s; %d errors in logs in 24 h%s; jobs waiting %s, failed %s.', $h['status'], $h['errors'], $h['lastErrors'] ? ' (latest: '.$h['lastErrors'][0]['message'].')' : '', $h['queued'] ?? '?', $h['failed'] ?? '?'),
            'The owner can open the dashboard from the panel (grid icon) or at /_cadenza/dashboard.',
        ];

        return implode("\n", $out);
    }
}
