<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Widget;

use Cadenza\Runtime\Entity\Widget;
use Cadenza\Runtime\Entity\WidgetRevision;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Widgets the agent wrote for this site. Their code is not trusted: it only ever runs in a sandboxed frame (see
 * WidgetController), so the checks here are about helping the agent, not about safety.
 */
final class Widgets
{
    public const MAX_SIZE = 300_000;

    /** Where models usually take libraries from; a widget gets the site's own copy instead. */
    private const CDN = '~(["\'])https?://(?:cdn\.jsdelivr\.net/npm|unpkg\.com|cdn\.skypack\.dev|esm\.sh|ga\.jspm\.io/npm:)/?((?:@[a-z0-9.-]+/)?[a-z0-9.-]+)(?:@[^/"\']*)?(/[^"\']*)?\1~i';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WidgetLibraries $libraries,
    ) {
    }

    public function get(string $name): ?Widget
    {
        return $this->em->getRepository(Widget::class)->findOneBy(['name' => $name]);
    }

    /**
     * The code of one version of a widget (the latest when that version was not kept).
     */
    public function code(Widget $widget, ?int $version): string
    {
        if (null === $version || $version === $widget->getVersion()) {
            return $widget->getCode();
        }
        $revision = $this->em->getRepository(WidgetRevision::class)->findOneBy(['widget' => $widget->getName(), 'version' => $version]);

        return $revision?->getCode() ?? $widget->getCode();
    }

    /**
     * The version of each widget a page of this document shows: the one it pins, else the latest.
     *
     * @param array<string, mixed> $document
     */
    public static function pinned(array $document, Widget $widget): int
    {
        return (int) ($document['widgets'][$widget->getName()] ?? $widget->getVersion());
    }

    /**
     * @return list<Widget>
     */
    public function all(): array
    {
        return $this->em->getRepository(Widget::class)->findBy([], ['name' => 'ASC']);
    }

    /**
     * Creates a widget, or a new version of one this site already has.
     *
     * @return array{widget: ?Widget, errors: list<string>, notes: list<string>}
     */
    public const SHAPES = ['wide', 'square', 'tall', 'banner', 'auto', 'background'];

    public function save(string $name, string $label, string $description, string $code, ?string $shape = null): array
    {
        $errors = [];
        $notes = [];
        if (!preg_match('/^[a-z][a-z0-9-]{2,38}$/', $name)) {
            $errors[] = 'name: lowercase letters, digits and dashes, e.g. "frogger".';
        }
        $code = trim(self::stripFence($code));
        if (preg_match('~<script[^>]+src=["\']?(?:https?:)?//~i', $code)) {
            $errors[] = 'code: scripts from other servers are blocked. Use <script type="module"> and import one of the site\'s libraries by name: '.implode(', ', array_keys($this->libraries->all())).'.';
        }
        [$code, $rewritten] = $this->useOwnLibraries($code);
        if ([] !== $rewritten) {
            $notes[] = 'Libraries now come from the site itself: '.implode(', ', $rewritten).'.';
        }
        if ('' === $code) {
            $errors[] = 'code: empty.';
        } elseif (\strlen($code) > self::MAX_SIZE) {
            $errors[] = \sprintf('code: %d KB is too big (at most %d KB).', \strlen($code) >> 10, self::MAX_SIZE >> 10);
        }
        if (preg_match('~\bimport\b[^;\'"]*?["\'](?:https?:)?//~i', $code)) {
            $errors[] = 'code: scripts from other servers are blocked. Import one of the site\'s libraries by name instead: '.implode(', ', array_keys($this->libraries->all())).'.';
        }
        $known = $this->libraries->specifiers();
        if (preg_match_all('~\b(?:import\b[^;\'"]*?|import\s*\()\s*["\']([^"\'./][^"\']*)["\']~', $code, $m)) {
            foreach (array_unique($m[1]) as $specifier) {
                if (!isset($known[$specifier]) && !str_contains($specifier, '://')) {
                    $errors[] = \sprintf('code: there is no library "%s" here; available: %s.', $specifier, implode(', ', array_keys($this->libraries->all())));
                }
            }
        }
        if (preg_match('~<(?:html|head|body)[\s>]~i', $code)) {
            // A whole document: keep what is inside <body> and the styles of <head>.
            $code = self::bodyOf($code);
        }
        if ([] !== $errors) {
            return ['widget' => null, 'errors' => $errors, 'notes' => $notes];
        }
        $widget = $this->get($name);
        if (null !== $widget) {
            $widget->revise('' !== $label ? $label : $widget->getLabel(), '' !== $description ? $description : $widget->getDescription(), $code);
        } else {
            $widget = new Widget($name, $label, $description, $code);
            $this->em->persist($widget);
        }
        $this->em->persist(new WidgetRevision($name, $widget->getVersion(), $widget->getLabel(), $code));
        if (\in_array($shape, self::SHAPES, true)) {
            $widget->setShape($shape);
        }
        $this->em->flush();

        return ['widget' => $widget, 'errors' => [], 'notes' => $notes];
    }

    public function reportError(string $name, int $version, string $error): bool
    {
        $widget = $this->get($name);
        if (null === $widget || $version < 1 || $version > $widget->getVersion()) {
            return false;
        }
        $error = ($version !== $widget->getVersion() ? 'version '.$version.' (shown on the live site): ' : '').$error;
        if ($widget->getLastError() === $error) {
            return false;
        }
        $widget->reportError($error);
        $this->em->flush();

        return true;
    }

    /**
     * The best results of a game, highest first.
     *
     * @return list<array{player: string, score: int, at: string}>
     */
    public function topScores(string $name, int $limit = 10): array
    {
        $scores = $this->em->getRepository(\Cadenza\Runtime\Entity\WidgetScore::class)->findBy(['widget' => $name], ['score' => 'DESC', 'id' => 'ASC'], $limit);

        return array_map(static fn ($s): array => $s->toArray(), $scores);
    }

    /**
     * Keeps a visitor's result. Names are plain: letters, digits, spaces and a few signs, at most 24 characters.
     */
    public function addScore(string $name, string $player, int $score): bool
    {
        $player = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[^\p{L}\p{N} ._\'-]/u', '', $player)));
        $player = mb_substr($player, 0, 24);
        if (null === $this->get($name) || '' === $player || $score < 0 || $score > 1_000_000_000) {
            return false;
        }
        $this->em->persist(new \Cadenza\Runtime\Entity\WidgetScore($name, $player, $score));
        $this->em->flush();

        return true;
    }

    public function clearScores(string $name): int
    {
        return (int) $this->em->createQuery('DELETE FROM '.\Cadenza\Runtime\Entity\WidgetScore::class.' s WHERE s.widget = :w')->setParameter('w', $name)->execute();
    }

    private static function stripFence(string $code): string
    {
        return preg_match('~^\s*```[a-z]*\s*\n(.*)\n```\s*$~s', $code, $m) ? $m[1] : $code;
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function useOwnLibraries(string $code): array
    {
        $known = $this->libraries->specifiers();
        $rewritten = [];
        $code = (string) preg_replace_callback(self::CDN, static function (array $m) use ($known, &$rewritten): string {
            $package = $m[2];
            $path = $m[3] ?? '';
            $specifier = match (true) {
                '' === $path, str_starts_with($path, '/build/'), '/+esm' === $path => $package,
                (bool) preg_match('~^/examples/jsm/(.+)$~', $path, $p) => $package.'/addons/'.$p[1],
                default => $package.$path,
            };
            $specifier = $known[$specifier] ?? $known[preg_replace('~\.js$~', '', $specifier).'.js'] ?? null;
            if (null === $specifier) {
                return $m[0];
            }
            $rewritten[] = $specifier;

            return $m[1].$specifier.$m[1];
        }, $code);

        return [$code, array_values(array_unique($rewritten))];
    }

    private static function bodyOf(string $html): string
    {
        $styles = preg_match_all('~<style\b[^>]*>.*?</style>~is', preg_replace('~<body\b.*$~is', '', $html) ?? '', $m) ? implode("\n", $m[0]) : '';
        $body = preg_match('~<body\b[^>]*>(.*)</body>~is', $html, $b) ? $b[1] : preg_replace('~</?(?:!doctype|html|head|body)[^>]*>|<(?:meta|title|link)\b[^>]*>(?:[^<]*</title>)?~i', '', $html);

        return trim($styles."\n".$body);
    }
}
