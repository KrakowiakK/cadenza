<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Draft;

use Cadenza\Runtime\Entity\SiteVersion;
use Cadenza\Runtime\Entity\Widget;
use Cadenza\Runtime\Operation\OperationApplier;
use Cadenza\Runtime\Repository\SiteVersionRepository;
use Cadenza\Runtime\Site\Diff;
use Cadenza\Runtime\Site\Document;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The only way the site changes: operations become a draft (a sketch), and only publishing makes a draft live.
 */
final class SiteManager
{
    public function __construct(
        private readonly SiteVersionRepository $versions,
        private readonly OperationApplier $applier,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function document(): array
    {
        return $this->versions->current()?->getDocument() ?? Document::empty();
    }

    public function currentNumber(): int
    {
        return $this->versions->current()?->getNumber() ?? 0;
    }

    /**
     * Creates a draft from the live site, or extends an existing draft with more operations.
     *
     * @param list<array<string, mixed>> $operations
     */
    public function propose(array $operations, string $summary, ?int $draftId = null): DraftResult
    {
        $draft = null;
        if (null !== $draftId) {
            $draft = $this->versions->draft($draftId) ?? throw new \InvalidArgumentException(\sprintf('Draft %d does not exist or was already published/discarded.', $draftId));
            $baseOperations = $draft->getOperations();
        } else {
            $baseOperations = [];
        }

        // Undo: an earlier published version, as a sketch like any other change.
        foreach ($operations as $i => $op) {
            if (\is_array($op) && 'site.restore' === ($op['op'] ?? null) && null !== ($old = $this->versions->byNumber((int) ($op['version'] ?? 0)))) {
                $operations[$i] = ['op' => 'site.replace', 'version' => $old->getNumber(), 'document' => $old->getDocument()];
            }
        }
        $base = $this->document();
        $usedIds = Document::ids($draft?->getDocument() ?? $base);
        $allOperations = [...$baseOperations, ...$this->applier->assignIds(array_merge($usedIds, Document::ids($base)), array_values($operations))];
        $result = $this->applier->apply($base, $allOperations);
        if (!$result->isValid()) {
            return DraftResult::invalid($result->errors);
        }
        // A widget placed on a page shows the version it was placed with, until a sketch brings a newer one.
        if ([] !== $pins = $this->unpinnedWidgets($result->document)) {
            $allOperations = [...$allOperations, ...$pins];
            $result = $this->applier->apply($base, $allOperations);
        }

        $diff = Diff::between($base, $result->document);
        if ($diff->isEmpty()) {
            return DraftResult::invalid(['The operations change nothing.']);
        }

        if (null === $draft) {
            $draft = new SiteVersion($result->document, $allOperations, mb_substr($summary, 0, 255), $this->currentNumber());
            $this->em->persist($draft);
        } else {
            $draft->revise($result->document, $allOperations, mb_substr($summary, 0, 255), $this->currentNumber());
        }
        $this->em->flush();

        return DraftResult::valid($draft, $diff, $result->created);
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return list<array{op: string, widget: string, version: int}>
     */
    private function unpinnedWidgets(array $document): array
    {
        $names = [];
        foreach (Document::walk($document) as $entry) {
            $name = $entry['node']['props']['widget'] ?? null;
            if (\is_string($name) && '' !== $name && !isset($document['widgets'][$name])) {
                $names[$name] = true;
            }
        }
        $pins = [];
        foreach ($names as $name => $_) {
            $widget = $this->em->getRepository(Widget::class)->findOneBy(['name' => $name]);
            if (null !== $widget) {
                $pins[] = ['op' => 'widget.use', 'widget' => $name, 'version' => $widget->getVersion()];
            }
        }

        return $pins;
    }

    public function diff(SiteVersion $draft): Diff
    {
        return Diff::between($this->document(), $this->rebased($draft));
    }

    /**
     * The draft applied to the current live site (other drafts may have been published since it was made).
     *
     * @return array<string, mixed>
     */
    public function rebased(SiteVersion $draft): array
    {
        if ($draft->getBaseNumber() === $this->currentNumber()) {
            return $draft->getDocument();
        }
        $result = $this->applier->apply($this->document(), $draft->getOperations());
        if (!$result->isValid()) {
            throw new \RuntimeException('The draft no longer applies to the live site: '.implode(' ', $result->errors));
        }

        return $result->document;
    }

    public function publish(int $draftId): SiteVersion
    {
        $draft = $this->versions->draft($draftId) ?? throw new \InvalidArgumentException(\sprintf('Draft %d does not exist or is not a draft.', $draftId));
        $draft->publish($this->currentNumber() + 1, $this->rebased($draft));
        $this->em->flush();

        return $draft;
    }

    public function discard(int $draftId): void
    {
        $draft = $this->versions->draft($draftId) ?? throw new \InvalidArgumentException(\sprintf('Draft %d does not exist or is not a draft.', $draftId));
        $draft->discard();
        $this->em->flush();
    }

    /**
     * Brings back the site as it was at the given published version, as a new version (history is never rewritten).
     */
    public function restore(int $number): SiteVersion
    {
        $old = $this->versions->byNumber($number) ?? throw new \InvalidArgumentException(\sprintf('Version %d does not exist.', $number));
        $version = new SiteVersion($old->getDocument(), [], \sprintf('Restored version %d (%s)', $number, $old->getSummary()), $this->currentNumber());
        $version->publish($this->currentNumber() + 1, $old->getDocument());
        $this->em->persist($version);
        $this->em->flush();

        return $version;
    }

    /**
     * Removes every version and draft and starts again from a blank canvas. Only for development and explicit resets.
     */
    public function wipe(string $name = 'My site'): SiteVersion
    {
        $this->em->createQuery('DELETE FROM '.SiteVersion::class.' v')->execute();

        return $this->initialise(Document::empty($name), 'Blank canvas');
    }

    /**
     * @param array<string, mixed> $document
     */
    public function initialise(array $document, string $summary): SiteVersion
    {
        $version = new SiteVersion($document, [], $summary, null);
        $version->publish($this->currentNumber() + 1, $document);
        $this->em->persist($version);
        $this->em->flush();

        return $version;
    }
}
