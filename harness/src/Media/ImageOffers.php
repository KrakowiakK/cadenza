<?php

declare(strict_types=1);

namespace App\Media;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Remembers image candidates (search results the agent saw) and offers (a grid the owner picks from for one node).
 */
final class ImageOffers
{
    public function __construct(private readonly CacheItemPoolInterface $cacheApp)
    {
    }

    /**
     * @param list<array<string, mixed>> $candidates
     */
    public function rememberCandidates(array $candidates): void
    {
        foreach ($candidates as $candidate) {
            $item = $this->cacheApp->getItem('cz_img_'.md5($candidate['id']));
            $item->set($candidate)->expiresAfter(86400 * 3);
            $this->cacheApp->saveDeferred($item);
        }
        $this->cacheApp->commit();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function candidate(string $id): ?array
    {
        $item = $this->cacheApp->getItem('cz_img_'.md5($id));

        return $item->isHit() ? $item->get() : null;
    }

    /**
     * @param array<string, mixed> $offer nodeId, prop, label, draftId, conversationId, candidates
     */
    public function create(array $offer): string
    {
        $id = strtolower((string) new Ulid());
        $this->rememberCandidates($offer['candidates']);
        $item = $this->cacheApp->getItem('cz_offer_'.$id);
        $item->set($offer + ['id' => $id])->expiresAfter(86400 * 3);
        $this->cacheApp->save($item);

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function offer(string $id): ?array
    {
        $item = $this->cacheApp->getItem('cz_offer_'.preg_replace('/[^a-z0-9]/', '', $id));

        return $item->isHit() ? $item->get() : null;
    }

    /**
     * @param array<string, mixed> $offer
     */
    public function update(array $offer): void
    {
        $item = $this->cacheApp->getItem('cz_offer_'.$offer['id']);
        $item->set($offer)->expiresAfter(86400 * 3);
        $this->cacheApp->save($item);
    }

    /**
     * The photos to show when the owner keeps this proposal (one of several looks): remembered until they choose.
     */
    public function awaitProposal(string $proposalId, string $offerId): void
    {
        $item = $this->cacheApp->getItem('cz_offer_for_'.md5($proposalId));
        $item->set($offerId)->expiresAfter(86400 * 3);
        $this->cacheApp->save($item);
    }

    /**
     * @return array<string, mixed>|null the offer waiting for this proposal, in the shape the panel shows
     */
    public function forProposal(string $proposalId): ?array
    {
        $item = $this->cacheApp->getItem('cz_offer_for_'.md5($proposalId));
        $offer = $item->isHit() ? $this->offer((string) $item->get()) : null;
        if (null === $offer) {
            return null;
        }

        return [
            'offerId' => $offer['id'],
            'label' => $offer['label'],
            'candidates' => array_map(static fn (array $r): array => array_intersect_key($r, array_flip(['id', 'thumb', 'title', 'author', 'license', 'source', 'width', 'height'])), $offer['candidates']),
        ];
    }
}
