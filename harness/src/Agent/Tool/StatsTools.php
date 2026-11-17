<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Studio\SiteClient;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('site_overview', 'The owner\'s dashboard as numbers: visits (this week, the week before, per day, most visited pages), content, recent changes and whether the application is healthy. Use it when the owner asks how the site is doing, how many people visited, what changed lately, or whether everything works.', method: 'read')]
#[AsTool('visitor_questions', 'What visitors asked the assistant on the site (the "ask" block), and above all the questions the site could not answer. Use it when the owner asks what visitors want to know, what is missing from the site, or what to put in a FAQ. Then offer to add the missing answers: ask the owner for the facts (never invent them) and sketch them into the page.', method: 'questions')]
#[AsTool('check_site', 'Check the published site for problems a visitor would stumble on, without guessing: links that lead nowhere, placeholders left behind, filler text, pages without a description, no way to get in touch, no site name, questions visitors asked that the site could not answer. Use it when the owner asks to check, review or audit the site, asks "is anything broken?" or "what should I improve?". Report the findings briefly in plain words (no node ids), most important first; fix in one sketch what needs no facts from the owner (broken links to existing sections, descriptions), and ask for the facts the rest needs.', method: 'check')]
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

    public function questions(): string
    {
        $q = $this->site->visitorQuestions();
        if (!isset($q['total'])) {
            return 'Cannot read the visitors\' questions right now.';
        }
        if (0 === $q['total']) {
            return 'No visitor has asked anything yet. (The assistant answers only where an "ask" block is on a published page.)';
        }
        $line = static fn (array $x): string => \sprintf('- "%s" (%s, on %s)', $x['question'], substr((string) $x['at'], 0, 10), $x['page']);
        $out = [\sprintf('%d questions so far, %d in the last 7 days; the site could not answer %d of them.', $q['total'], $q['week'], $q['unanswered'])];
        if ([] !== $q['missing']) {
            $out[] = 'NOT ANSWERED by the site (what visitors look for and do not find):';
            array_push($out, ...array_map($line, \array_slice($q['missing'], 0, 20)));
        }
        $out[] = 'Newest questions:';
        array_push($out, ...array_map(static fn (array $x): string => $line($x).($x['answered'] ? '' : ' — no answer'), \array_slice($q['recent'], 0, 15)));

        return implode("\n", $out);
    }

    public function check(): string
    {
        $r = $this->site->review();
        if (!isset($r['findings'])) {
            return 'Cannot check the site right now.';
        }
        $out = [];
        foreach ($r['findings'] as $f) {
            $out[] = \sprintf('- [%s] page %s%s: %s Fix: %s', $f['kind'], $f['page'], null !== ($f['node'] ?? null) ? ', node '.$f['node'] : '', $f['problem'], $f['fix']);
        }
        if (($r['unansweredQuestions'] ?? 0) > 0) {
            $out[] = \sprintf('- [questions] Visitors asked %d question(s) the site could not answer: visitor_questions lists them.', $r['unansweredQuestions']);
        }

        return [] === $out
            ? 'No problems found: every link leads somewhere, no placeholders or filler text, every page has a description, visitors can get in touch. (This check does not judge the wording or the look; check_on_phone looks at the page on a phone.)'
            : \count($out)." finding(s) on the published site:\n".implode("\n", $out);
    }
}
