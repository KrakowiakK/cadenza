<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Stats;

use Cadenza\Runtime\Collection\Collections;
use Cadenza\Runtime\Connector\Connectors;
use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Entity\ImportJob;
use Cadenza\Runtime\Form\Forms;
use Cadenza\Runtime\Media\MediaLibrary;
use Cadenza\Runtime\Repository\SiteVersionRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

/**
 * Everything the owner may want to know about their site in one place: visitors, answers, changes, content and
 * whether the application is healthy. Used by the dashboard and by the agent.
 */
final class SiteStats
{
    public function __construct(
        private readonly Connection $db,
        private readonly EntityManagerInterface $em,
        private readonly SiteManager $site,
        private readonly SiteVersionRepository $versions,
        private readonly Forms $forms,
        private readonly MediaLibrary $media,
        private readonly Collections $collections,
        private readonly Connectors $connectors,
        private readonly \Cadenza\Runtime\Widget\Widgets $widgets,
        #[Autowire(service: 'messenger.receiver_locator')]
        private readonly ContainerInterface $receivers,
        #[Autowire('%kernel.logs_dir%/%kernel.environment%.log')]
        private readonly string $logFile,
        private readonly WorkerHeartbeat $heartbeat,
    ) {
    }

    /**
     * Page views per day (oldest first, days without visits included), totals and the most visited pages.
     *
     * @return array{days: list<array{day: string, views: int}>, total: int, week: int, previousWeek: int, today: int, pages: list<array{path: string, views: int}>}
     */
    public function visits(int $days = 30): array
    {
        $from = new \DateTimeImmutable(\sprintf('today -%d days', $days - 1));
        $rows = $this->db->fetchAllKeyValue('SELECT day, SUM(views) FROM cadenza_page_view WHERE day >= ? GROUP BY day', [$from->format('Y-m-d')]);
        $series = [];
        for ($d = $from; $d <= new \DateTimeImmutable('today'); $d = $d->modify('+1 day')) {
            $series[] = ['day' => $d->format('Y-m-d'), 'views' => (int) ($rows[$d->format('Y-m-d')] ?? 0)];
        }
        $sum = static fn (array $slice): int => array_sum(array_column($slice, 'views'));
        $pages = $this->db->fetchAllAssociative('SELECT path, SUM(views) AS views FROM cadenza_page_view WHERE day >= ? GROUP BY path ORDER BY views DESC LIMIT 6', [$from->format('Y-m-d')]);

        return [
            'days' => $series,
            'total' => $sum($series),
            'week' => $sum(\array_slice($series, -7)),
            'previousWeek' => $sum(\array_slice($series, -14, 7)),
            'today' => end($series)['views'] ?? 0,
            'pages' => array_map(static fn (array $r): array => ['path' => (string) $r['path'], 'views' => (int) $r['views']], $pages),
        ];
    }

    /**
     * @return array{pages: int, blocks: int, version: int, publishedAt: ?string, history: list<array{number: ?int, summary: string, at: ?string}>, media: int, albums: int, collections: list<array{name: string, slug: string, records: int}>}
     */
    public function content(): array
    {
        $doc = $this->site->document();
        $blocks = 0;
        foreach (\Cadenza\Runtime\Site\Document::walk($doc) as $_) {
            ++$blocks;
        }
        $history = $this->versions->history(6);
        $media = $this->media->stats();

        return [
            'pages' => \count($doc['pages']),
            'blocks' => $blocks,
            'version' => $this->site->currentNumber(),
            'publishedAt' => ($history[0] ?? null)?->getPublishedAt()?->format(\DATE_ATOM),
            'history' => array_map(static fn ($v): array => ['number' => $v->getNumber(), 'summary' => $v->getSummary(), 'at' => $v->getPublishedAt()?->format(\DATE_ATOM)], $history),
            'media' => $media['total'],
            'albums' => \count($media['albums']),
            'collections' => array_map(fn ($c): array => ['name' => $c->getName(), 'slug' => $c->getSlug(), 'records' => \count($this->collections->records($c))], $this->collections->all()),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forms(): array
    {
        return $this->forms->all();
    }

    /**
     * Is everything working: recent errors in the log, background jobs waiting or failed, imports, connected accounts.
     *
     * @return array{status: string, errors: int, lastErrors: list<array{at: string, message: string}>, queued: ?int, worker: bool, workerSeen: ?string, failed: ?int, imports: list<array<string, mixed>>, connections: list<array<string, mixed>>, versions: array<string, string>}
     */
    public function health(): array
    {
        [$errors, $lastErrors] = $this->recentErrors();
        $queued = $this->count('async');
        $failed = $this->count('failed');
        $seen = $this->heartbeat->lastSeen();
        // The worker checks in every 30 s; two minutes of silence means nobody processes background jobs.
        $worker = null !== $seen && time() - $seen < 120;
        $imports = array_map(static fn (ImportJob $j): array => $j->toArray(), $this->em->getRepository(ImportJob::class)->findBy([], ['id' => 'DESC'], 3));
        // Widgets a browser reported as broken while the owner looked at them.
        $brokenWidgets = [];
        foreach ($this->widgets->all() as $widget) {
            if (null !== $widget->getLastError()) {
                $brokenWidgets[] = ['name' => $widget->getName(), 'label' => $widget->getLabel(), 'error' => $widget->getLastError()];
            }
        }
        $connections = [];
        foreach ($this->connectors->all() as $name => $_) {
            if (null !== $c = $this->connectors->connection($name)) {
                $connections[] = $c->toArray();
            }
        }

        return [
            'status' => match (true) {
                $failed > 0 || $errors >= 10 || (!$worker && $queued > 0) => 'attention',
                $errors > 0 || $queued > 20 || !$worker || [] !== $brokenWidgets => 'warning',
                default => 'good',
            },
            'errors' => $errors,
            'lastErrors' => $lastErrors,
            'queued' => $queued,
            'worker' => $worker,
            'workerSeen' => null !== $seen ? date(\DATE_ATOM, $seen) : null,
            'failed' => $failed,
            'imports' => $imports,
            'connections' => $connections,
            'widgets' => $brokenWidgets,
            'versions' => [
                'PHP' => \PHP_VERSION,
                'Symfony' => Kernel::VERSION,
                'Symfony AI' => self::package('symfony/mcp-bundle'),
                'MCP SDK' => self::package('mcp/sdk'),
            ],
        ];
    }

    private function count(string $transport): ?int
    {
        try {
            $receiver = $this->receivers->has($transport) ? $this->receivers->get($transport) : null;

            return $receiver instanceof MessageCountAwareInterface ? $receiver->getMessageCount() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Errors logged in the last 24 hours (read from the end of the log file).
     *
     * @return array{0: int, 1: list<array{at: string, message: string}>}
     */
    private function recentErrors(): array
    {
        if (!is_file($this->logFile) || false === $handle = @fopen($this->logFile, 'r')) {
            return [0, []];
        }
        $size = (int) filesize($this->logFile);
        fseek($handle, max(0, $size - 2_000_000));
        $since = new \DateTimeImmutable('-24 hours');
        $found = [];
        while (false !== $line = fgets($handle)) {
            // Application faults only: a visitor's 404/403 or a mistyped console command is not the site failing.
            if (preg_match('/^\[([^\]]+)\] (?!console\.)[\w.-]+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.{0,220})/', $line, $m)
                && !preg_match('/(NotFound|AccessDenied|MethodNotAllowed|BadRequest|TooManyRequests)HttpException/', $m[3])) {
                try {
                    $at = new \DateTimeImmutable($m[1]);
                } catch (\Exception) {
                    continue;
                }
                if ($at >= $since) {
                    $found[] = ['at' => $at->format(\DATE_ATOM), 'message' => trim(preg_replace('/\s+\{.*$/', '', $m[3]) ?? $m[3])];
                }
            }
        }
        fclose($handle);

        return [\count($found), \array_slice(array_reverse($found), 0, 3)];
    }

    private static function package(string $name): string
    {
        try {
            return \Composer\InstalledVersions::getPrettyVersion($name) ?? '—';
        } catch (\Throwable) {
            return '—';
        }
    }
}
