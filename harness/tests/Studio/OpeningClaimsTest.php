<?php

declare(strict_types=1);

namespace App\Tests\Studio;

use App\Entity\Conversation;
use App\Studio\OpeningClaims;
use PHPUnit\Framework\TestCase;

final class OpeningClaimsTest extends TestCase
{
    public function testOpeningTimesTheOwnerNeverGaveLeaveANewBlock(): void
    {
        $conversation = new Conversation();
        $conversation->addUserTurn('A small bookshop in Edinburgh, Page & Thistle. How could our website look?', [], '/');
        $ops = [['op' => 'page.create', 'path' => '/', 'nodes' => [['type' => 'hero', 'props' => ['title' => 'Page & Thistle', 'subtitle' => 'Curated books, quiet corners, and good coffee nearby. Open daily.']]]]];

        [$clean, $removed] = OpeningClaims::strip($ops, $conversation);

        self::assertSame(1, $removed);
        self::assertSame('Curated books, quiet corners, and good coffee nearby.', $clean[0]['nodes'][0]['props']['subtitle']);
    }

    public function testWhatTheOwnerSaidAboutHoursStays(): void
    {
        $conversation = new Conversation();
        $conversation->addUserTurn('A bookshop, open Tuesday to Sunday 10 to 18.', [], '/');
        $ops = [['op' => 'node.insert', 'node' => ['type' => 'text', 'props' => ['text' => 'Open Tuesday to Sunday, 10:00 to 18:00.']]]];

        self::assertSame([$ops, 0], OpeningClaims::strip($ops, $conversation));
    }

    public function testAChangeToAnExistingBlockIsNotTouched(): void
    {
        $conversation = new Conversation();
        $conversation->addUserTurn('Make the opening friendlier.', [], '/');
        $ops = [['op' => 'node.update', 'id' => 'n1', 'props' => ['subtitle' => 'Open daily.']]];

        self::assertSame([$ops, 0], OpeningClaims::strip($ops, $conversation));
    }
}
