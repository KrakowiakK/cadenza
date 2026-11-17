<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Mcp\Agent;

use Cadenza\Runtime\Stats\SiteStats;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

/**
 * The owner's dashboard as data: visitors, content, recent changes and the application's health.
 */
final class StatsTools
{
    public function __construct(
        private readonly SiteStats $stats,
        private readonly \Cadenza\Runtime\Ask\VisitorQuestions $questions,
        private readonly \Cadenza\Runtime\Draft\SiteManager $site,
        private readonly \Cadenza\Runtime\Collection\Collections $collections,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'site_stats', description: 'Overview of the site: visits per day for 30 days (counted without cookies, owner and bots excluded), this week vs the week before, most visited pages, content, recent published versions, and health (errors in logs in 24 h, background jobs waiting or failed, imports, connected accounts, versions).', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function stats(): array
    {
        $visits = $this->stats->visits(30);
        $visits['days'] = array_values(array_filter($visits['days'], static fn (array $d): bool => $d['views'] > 0));

        return ['visits' => $visits, 'content' => $this->stats->content(), 'health' => $this->stats->health(), 'dashboard' => '/_cadenza/dashboard'];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'visitor_questions', description: 'What visitors asked the site\'s assistant (the "ask" block): totals, the newest questions, and "missing": the ones the site\'s content could not answer. Those show what to add to the site.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function visitorQuestions(int $limit = 30): array
    {
        return $this->questions->summary(max(1, min(100, $limit)));
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'site_review', description: 'A check of the published site that needs no model: links that lead nowhere, placeholders left behind, filler text, pages without a description, no way to get in touch, a site without a name. Each finding names the page and the node, and how to fix it.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function review(): array
    {
        $findings = \Cadenza\Runtime\Site\Review::of($this->site->document(), array_map(static fn ($c): string => $c->getSlug(), $this->collections->all()));

        return ['findings' => $findings, 'unansweredQuestions' => $this->questions->summary(5)['unanswered']];
    }
}
