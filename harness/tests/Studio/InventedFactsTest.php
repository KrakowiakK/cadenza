<?php

declare(strict_types=1);

namespace App\Tests\Studio;

use App\Entity\Conversation;
use App\Studio\InventedFacts;
use PHPUnit\Framework\TestCase;

final class InventedFactsTest extends TestCase
{
    private const INVENTED = ['type' => 'testimonials', 'props' => ['title' => 'What students say', 'items' => [
        ['text' => 'The best studio in town, I come every week.', 'author' => 'Anna K.'],
        ['text' => 'Calm, kind teachers.', 'author' => 'Marek'],
    ]]];

    public function testReviewsTheOwnerNeverGaveAreTakenOut(): void
    {
        $conversation = new Conversation();
        $conversation->addUserTurn('I run a small yoga studio in Gdynia. How could my website look?', [], '/');

        [$ops, $removed] = InventedFacts::strip([
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

        [$ops, $removed] = InventedFacts::strip([['op' => 'node.insert', 'page' => '/', 'node' => self::INVENTED]], $conversation);

        self::assertSame(1, $removed);
        self::assertSame([['text' => 'Calm, kind teachers.', 'author' => 'Marek']], $ops[0]['node']['props']['items']);
    }

    public function testAnAttachedFileMayHoldTheReviews(): void
    {
        $conversation = new Conversation();
        $conversation->addUserTurn('Here are our reviews, put them on the site.', [], '/', [['id' => 'a1', 'kind' => 'document', 'name' => 'reviews.pdf', 'url' => '/u/a1']]);

        self::assertSame([[['op' => 'node.insert', 'page' => '/', 'node' => self::INVENTED]], 0, 0], InventedFacts::strip([['op' => 'node.insert', 'page' => '/', 'node' => self::INVENTED]], $conversation));
    }

    public function testPricesTheOwnerNeverGaveAreTakenOut(): void
    {
        $conversation = new Conversation();
        $conversation->addUserTurn('A website for my café. A flat white is 4,50 and a drop-in class costs 40 zł.', [], '/');

        [$ops, $reviews, $prices] = InventedFacts::strip([['op' => 'page.create', 'path' => '/', 'title' => 'Café', 'nodes' => [
            ['type' => 'menu', 'props' => ['categories' => [['name' => 'Coffee', 'items' => [
                ['name' => 'Flat white', 'price' => '£4.50'],
                ['name' => 'Espresso', 'price' => '£3.00'],
                ['name' => 'Water', 'price' => 'free'],
            ]]]]],
            ['type' => 'pricing', 'props' => ['plans' => [
                ['name' => 'Drop-in', 'price' => '40 zł'],
                ['name' => 'Monthly', 'price' => '280 zł'],
            ]]],
        ]]], $conversation);

        self::assertSame([0, 2], [$reviews, $prices]);
        self::assertSame([['name' => 'Flat white', 'price' => '£4.50'], ['name' => 'Espresso'], ['name' => 'Water', 'price' => 'free']], $ops[0]['nodes'][0]['props']['categories'][0]['items']);
        self::assertSame([['name' => 'Drop-in', 'price' => '40 zł']], $ops[0]['nodes'][1]['props']['plans']);
    }

    public function testWhatThePublishedSiteAlreadySaysIsGiven(): void
    {
        $conversation = new Conversation();
        $conversation->addUserTurn('Move the price list to a page of its own.', [], '/');
        $ops = [['op' => 'page.create', 'path' => '/prices', 'title' => 'Prices', 'nodes' => [['type' => 'pricing', 'props' => ['plans' => [['name' => 'Monthly', 'price' => '280 zł']]]]]]];

        self::assertSame(1, InventedFacts::strip($ops, $conversation)[2]);
        self::assertSame([$ops, 0, 0], InventedFacts::strip($ops, $conversation, static fn (): string => "## Page \"Home\" (/)\nMonthly\nprice: 280 zł"));
    }
}
