<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Studio\Proposals;
use App\Studio\SiteClient;
use App\Studio\Turn;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('propose_changes', 'Propose a change to the site as a batch of operations. It becomes a sketch the owner sees on the page immediately and approves or rejects. Returns the result, or errors to fix and retry.', method: 'propose')]
#[AsTool('propose_variants', 'Show 2–3 looks side by side (when the owner asks how the site could look, wants options, or starts from scratch). Give the page content once in "operations" (omit it to restyle the existing site) and 2–3 design directions, e.g. ["warm", "elegant", "minimal"]. The owner sees live thumbnails and keeps one.', method: 'variants')]
#[AsTool('read_page', 'Full JSON of one page with every prop value (the outline in your instructions shows only key props).', method: 'readPage')]
#[AsTool('read_outline', 'Fresh outline of the live site, or of an open draft when draftId is given.', method: 'readOutline')]
final class SiteTools
{
    public function __construct(
        private readonly SiteClient $site,
        private readonly Turn $turn,
        private readonly Proposals $proposals,
        private readonly \App\Repository\ProposalRepository $openProposals,
    ) {
    }

    /**
     * @param list<mixed>                $operations Operations of the editing language (see the guide), e.g. [{"op": "node.update", "id": "nab12c", "props": {"title": "…"}}]
     * @param string                     $summary    One short sentence in the owner's language saying what the sketch shows
     * @param int|null                   $reviseDraftId Id of an open draft of this conversation to extend with more operations (to adjust a sketch the owner is looking at); omit to start a new, separate proposal
     */
    public function propose(array $operations, string $summary = '', ?int $reviseDraftId = null): string
    {
        $summary = '' !== trim($summary) ? $summary : 'Zmiany na stronie';
        $result = $this->proposals->propose($this->turn->conversation, $operations, $summary, $reviseDraftId);
        if (null === $result['proposal']) {
            return "The site rejected the batch, nothing was saved. Fix these errors and call propose_changes again with the whole corrected batch:\n- ".implode("\n- ", $result['errors']);
        }
        $proposal = $result['proposal'];

        $this->turn->proposals[] = $proposal;
        $this->turn->emit('proposal', $proposal->toArray());
        $this->turn->emit('ui', ['command' => 'showSketch', 'draftId' => $proposal->getDraftId(), 'path' => $proposal->getFocusPath()]);

        return \sprintf(
            "Sketch ready: draft %d, shown to the owner on %s. Changes: %s.\nCreated: %s\nThe owner now reviews it and approves or rejects it in the panel; do not claim it is live.",
            $proposal->getDraftId(),
            $proposal->getFocusPath(),
            json_encode($proposal->toArray()['changes']),
            implode('; ', \array_slice($result['created'], 0, 40)) ?: 'nothing new',
        );
    }

    /**
     * @param list<mixed> $directions 2–3 of: minimal, editorial, warm, bold, elegant, fresh
     * @param list<mixed> $operations The page content written once (page.create with patterns…); omit to restyle the current site
     * @param string      $summary    One sentence about the content, in the owner's language
     */
    public function variants(array $directions = [], array $operations = [], string $summary = ''): string
    {
        $summary = '' !== trim($summary) ? $summary : 'Propozycja strony';
        // Models express the directions in many shapes; take the known direction names in the order given.
        preg_match_all('/\b('.implode('|', array_keys(self::HERO_FOR)).')\b/', json_encode($directions) ?: '', $m);
        $found = array_values(array_unique($m[1]));
        if (\count($found) < 2) {
            return 'Give 2–3 design directions, e.g. "directions": ["warm", "elegant", "minimal"].';
        }
        $operations = Proposals::normalise($operations);
        $group = strtolower((string) new \Symfony\Component\Uid\Ulid());
        $names = self::DIRECTION_NAMES[str_starts_with($this->turn->language, 'pl') ? 'pl' : 'en'];
        $made = [];
        $errors = [];
        foreach (\array_slice($found, 0, 3) as $i => $direction) {
            $name = $names[$direction];
            $ops = array_values(array_filter(self::restyle($operations, self::HERO_FOR[$direction]), static fn (mixed $o): bool => !\is_array($o) || 'theme.update' !== ($o['op'] ?? null)));
            $ops[] = ['op' => 'theme.update', 'direction' => $direction];
            $result = $this->proposals->propose($this->turn->conversation, $ops, $name.': '.$summary);
            if (null === $result['proposal']) {
                $errors[] = $name.': '.implode(' ', $result['errors']);
                if (0 === $i) {
                    break; // the content itself is wrong: no point trying the other looks
                }
                continue;
            }
            $proposal = $result['proposal'];
            $proposal->asVariant($group, $name);
            $this->proposals->flush();
            $this->turn->proposals[] = $proposal;
            $made[] = $proposal->toArray();
        }
        if ([] === $made) {
            return "No variant could be created. Fix the operations and call again:\n- ".implode("\n- ", $errors);
        }
        $this->turn->emit('variants', ['groupId' => $group, 'items' => $made]);

        return \sprintf('Showed %d variants side by side: %s. The owner picks one (or asks to mix them).%s', \count($made), implode(', ', array_column($made, 'variantName')), $errors ? "\nFailed: ".implode('; ', $errors) : '');
    }

    /** The hero layout that suits each direction best. */
    private const HERO_FOR = ['minimal' => 'typographic', 'editorial' => 'split', 'warm' => 'split', 'bold' => 'image', 'elegant' => 'image', 'fresh' => 'collage'];

    private const DIRECTION_NAMES = [
        'pl' => ['minimal' => 'Minimalistycznie', 'editorial' => 'Magazynowo', 'warm' => 'Ciepło', 'bold' => 'Odważnie', 'elegant' => 'Elegancko', 'fresh' => 'Świeżo'],
        'en' => ['minimal' => 'Minimal', 'editorial' => 'Editorial', 'warm' => 'Warm', 'bold' => 'Bold', 'elegant' => 'Elegant', 'fresh' => 'Fresh'],
    ];

    /**
     * Sets the hero variant on every hero node spec in the batch.
     *
     * @param list<mixed> $operations
     *
     * @return list<mixed>
     */
    private static function restyle(array $operations, ?string $hero): array
    {
        if (null === $hero) {
            return $operations;
        }
        $walk = static function (mixed $spec) use (&$walk, $hero): mixed {
            if (!\is_array($spec)) {
                return $spec;
            }
            if ('hero' === ($spec['type'] ?? null)) {
                $spec['props'] = ['variant' => $hero] + (\is_array($spec['props'] ?? null) ? $spec['props'] : []);
                $spec['props']['variant'] = $hero;
            }
            foreach (['nodes', 'children', 'actions'] as $key) {
                if (\is_array($spec[$key] ?? null)) {
                    $spec[$key] = array_map($walk, $spec[$key]);
                }
            }
            if (\is_array($spec['node'] ?? null)) {
                $spec['node'] = $walk($spec['node']);
            }

            return $spec;
        };

        return array_map($walk, $operations);
    }

    /**
     * @param string   $page    Page path like "/" or "/menu"
     * @param int|null $draftId Read the page as it is in this draft
     */
    public function readPage(string $page, ?int $draftId = null): string
    {
        $tree = $this->site->pageTree($page, $draftId);
        if (null !== $draftId || !str_contains($tree, 'does not exist') || null === $this->turn->conversation) {
            return $tree;
        }
        // A page that so far exists only in a sketch of this conversation: read it there, to keep working on it.
        foreach (array_reverse($this->openProposals->open($this->turn->conversation)) as $proposal) {
            $inSketch = $this->site->pageTree($page, $proposal->getDraftId());
            if (!str_contains($inSketch, 'does not exist')) {
                return \sprintf("(This page is not live yet: it is in sketch %d. To change it, call propose_changes with reviseDraftId %d.)\n%s", $proposal->getDraftId(), $proposal->getDraftId(), $inSketch);
            }
        }

        return $tree;
    }

    /**
     * @param int|null $draftId Outline of this draft instead of the live site
     */
    public function readOutline(?int $draftId = null): string
    {
        return $this->site->outline($draftId);
    }
}
