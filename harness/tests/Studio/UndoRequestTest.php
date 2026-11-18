<?php

declare(strict_types=1);

namespace App\Tests\Studio;

use App\Studio\UndoRequest;
use PHPUnit\Framework\TestCase;

final class UndoRequestTest extends TestCase
{
    public function testAnUndoIsRecognisedInTheOwnersWords(): void
    {
        foreach (['Undo that', 'undo', 'Please, undo the last change', 'Bring it back', 'Cofnij to', 'Przywróć poprzednią wersję', 'Make it how it was before'] as $text) {
            self::assertTrue(UndoRequest::matches($text), $text);
        }
    }

    public function testOrdinaryRequestsAreNot(): void
    {
        foreach (['Change the colours to dark green', 'Add a section about undoing bookings', 'Add a button "Back to the menu"', str_repeat('Undo that and also write a long new page about our history. ', 4)] as $text) {
            self::assertFalse(UndoRequest::matches($text), $text);
        }
    }

    public function testOnlyARestoreCountsAsAnUndo(): void
    {
        self::assertTrue(UndoRequest::restores([['op' => 'site.restore', 'version' => 3]]));
        self::assertFalse(UndoRequest::restores([['op' => 'theme.update', 'theme' => []]]));
    }
}
