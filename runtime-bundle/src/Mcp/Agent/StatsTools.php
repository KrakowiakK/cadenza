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
    public function __construct(private readonly SiteStats $stats)
    {
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
}
