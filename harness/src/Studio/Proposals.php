<?php

declare(strict_types=1);

namespace App\Studio;

use App\Entity\Conversation;
use App\Entity\Proposal;
use App\Repository\ProposalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Turns operations into a proposal: a new draft in the application, or a revision of an open one.
 */
final class Proposals
{
    public function __construct(
        private readonly SiteClient $site,
        private readonly ProposalRepository $proposals,
        private readonly EntityManagerInterface $em,
        private readonly WorkflowInterface $proposalStateMachine,
    ) {
    }

    /**
     * The owner reads the summary: no markup or leftovers of it (models sometimes end with "</" or a tag).
     */
    public static function cleanSummary(string $summary): string
    {
        $text = trim((string) preg_replace(['~</?[a-z!][^>]*>~i', '~\s+~u', '~</?$~'], ['', ' ', ''], trim($summary)), " \t\n\r\0\x0B<>[]{}");
        // Quotes only when they wrap the whole summary or hang alone at its end ("…osób\">").
        $text = (string) preg_replace('~^(["\'])(.*)\1$~su', '$2', $text);
        foreach (['"', "'"] as $quote) {
            if (str_ends_with($text, $quote) && 1 === substr_count($text, $quote) % 2) {
                $text = rtrim(substr($text, 0, -1));
            }
        }

        return '' !== $text ? mb_substr($text, 0, 240) : 'Changes';
    }

    /**
     * Models sometimes send each operation as a JSON string, or the whole list wrapped once more.
     *
     * @param array<mixed> $operations
     *
     * @return list<mixed>
     */
    public static function normalise(array $operations): array
    {
        $out = [];
        foreach (array_values($operations) as $op) {
            if (\is_string($op)) {
                $decoded = \App\Agent\JsonRepair::decode($op);
                if (\is_array($decoded) && array_is_list($decoded)) {
                    array_push($out, ...self::normalise($decoded));
                    continue;
                }
                $op = \is_array($decoded) ? $decoded : $op;
            }
            $out[] = $op;
        }

        return $out;
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    /**
     * @param list<array<string, mixed>> $operations
     *
     * @return array{proposal: ?Proposal, errors: list<string>, created: list<string>}
     */
    public function propose(Conversation $conversation, array $operations, string $summary, ?int $reviseDraftId = null): array
    {
        $proposal = null;
        if (null !== $reviseDraftId) {
            $proposal = $this->proposals->byDraft($reviseDraftId);
            if (null === $proposal || !$proposal->isOpen()) {
                $proposal = null;
                $reviseDraftId = null;
            }
        }

        $summary = self::cleanSummary($summary);
        $result = $this->site->propose(self::normalise($operations), $summary, $reviseDraftId);
        if (!($result['ok'] ?? false)) {
            return ['proposal' => null, 'errors' => array_values((array) ($result['errors'] ?? ['unknown error'])), 'created' => []];
        }

        $focus = (string) ($result['focusPath'] ?? '/');
        if (null === $proposal) {
            $proposal = new Proposal($conversation, (int) $result['draftId'], $summary, $result['changes'] ?? [], $focus);
            $this->em->persist($proposal);
            $this->proposalStateMachine->apply($proposal, 'sketch');
        } else {
            $proposal->revise($summary, $result['changes'] ?? [], $focus);
            $this->proposalStateMachine->apply($proposal, 'revise');
        }
        $this->em->flush();

        return ['proposal' => $proposal, 'errors' => [], 'created' => array_values((array) ($result['created'] ?? []))];
    }
}
