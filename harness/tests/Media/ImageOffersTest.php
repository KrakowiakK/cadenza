<?php

declare(strict_types=1);

namespace App\Tests\Media;

use App\Media\ImageOffers;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class ImageOffersTest extends TestCase
{
    public function testThePhotosOfALookWaitUntilTheOwnerKeepsIt(): void
    {
        $offers = new ImageOffers(new ArrayAdapter());
        $id = $offers->create([
            'nodeId' => 'n1', 'prop' => 'image', 'label' => 'Opening photo', 'query' => 'yoga studio', 'draftId' => 7, 'conversationId' => 'c1',
            'candidates' => [['id' => 'wm-1', 'thumb' => 't.jpg', 'url' => 'secret-full-size.jpg', 'title' => 'Yoga', 'author' => 'A', 'license' => 'CC0', 'source' => 'wikimedia', 'width' => 1200, 'height' => 800]],
        ]);
        $offers->awaitProposal('p1', $id);

        self::assertNull($offers->forProposal('other'));
        $shown = $offers->forProposal('p1');
        self::assertSame($id, $shown['offerId']);
        self::assertSame('Opening photo', $shown['label']);
        self::assertSame('wm-1', $shown['candidates'][0]['id']);
        self::assertArrayNotHasKey('url', $shown['candidates'][0], 'the panel gets thumbnails, not the originals');
    }
}
