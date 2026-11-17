<?php

declare(strict_types=1);

namespace App\Tests\Studio;

use App\Entity\Conversation;
use App\Studio\InventedReviews;
use PHPUnit\Framework\TestCase;

final class InventedReviewsTest extends TestCase
{
    private const INVENTED = ['type' => 'testimonials', 'props' => ['title' => 'What students say', 'items' => [
        ['text' => 'The best studio in town, I come every week.', 'author' => 'Anna K.'],
        ['text' => 'Calm, kind teachers.', 'author' => 'Marek'],
    ]]];

    public function testReviewsTheOwnerNeverGaveAreTakenOut(): void
    {
        $conversation = new Conversation();
        $conversation->addUserTurn('I run a small yoga studio in Gdynia. How could my website look?', [], '/');

        [$ops, $removed] = InventedReviews::strip([
            ['op' => 'page.create', 'path' => '/', 'title' => 'Home', 'nodes' => [['type' => 'hero', 'props' => ['title' => 'Yoga']], self::INVENTED]],
            ['op' => 'node.insert', 'page' => '/', 'node' => self::INVENTED],
            ['op' => 'theme.update', 'preset' => 'warm'],
        ], $conversation);

        self::assertSame(4, $removed);
        self::assertSame([
            ['op' => 'page.create', 'path' => '/', 'title' => 'Home', 'nodes' => [['type' => 'hero', 'props' => ['title' => 'Yoga']]]],
            ['op' => 'theme.update', 'preset' => 'warm'],
        ], $ops);
    }

    public function testReviewsInTheOwnersWordsStay(): void
    {
        $conversation = new Conversation();
        $conversation->addUserTurn('Add what people say. Marek wrote: "Calm, kind teachers." That is all I have.', [], '/');

        [$ops, $removed] = InventedReviews::strip([['op' => 'node.insert', 'page' => '/', 'node' => self::INVENTED]], $conversation);

        self::assertSame(1, $removed);
        self::assertSame([['text' => 'Calm, kind teachers.', 'author' => 'Marek']], $ops[0]['node']['props']['items']);
    }

    public function testAnAttachedFileMayHoldTheReviews(): void
    {
        $conversation = new Conversation();
        $conversation->addUserTurn('Here are our reviews, put them on the site.', [], '/', [['id' => 'a1', 'kind' => 'document', 'name' => 'reviews.pdf', 'url' => '/u/a1']]);

        self::assertSame([[['op' => 'node.insert', 'page' => '/', 'node' => self::INVENTED]], 0], InventedReviews::strip([['op' => 'node.insert', 'page' => '/', 'node' => self::INVENTED]], $conversation));
    }
}
