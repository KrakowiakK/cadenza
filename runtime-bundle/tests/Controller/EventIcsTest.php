<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Tests\Controller;

use Cadenza\Runtime\Controller\EventController;
use PHPUnit\Framework\TestCase;

final class EventIcsTest extends TestCase
{
    public function testTimedEventWithPlace(): void
    {
        $ics = EventController::ics(['title' => 'Ślub Ani i Tomka', 'date' => '2027-06-14', 'time' => '15:00', 'endTime' => '23:30', 'place' => 'Kazimierz Dolny, ul. Nadrzeczna 2'], 'Site', 'n1@example.com', 'https://example.com');

        self::assertStringContainsString("DTSTART:20270614T150000\r\n", $ics);
        self::assertStringContainsString("DTEND:20270614T233000\r\n", $ics);
        self::assertStringContainsString('SUMMARY:Ślub Ani i Tomka', $ics);
        self::assertStringContainsString('LOCATION:Kazimierz Dolny\, ul. Nadrzeczna 2', $ics);
    }

    public function testAllDayEvent(): void
    {
        $ics = EventController::ics(['date' => '2027-06-14'], 'Wedding', 'n1@example.com', 'https://example.com');

        self::assertStringContainsString("DTSTART;VALUE=DATE:20270614\r\nDTEND;VALUE=DATE:20270615", $ics);
        self::assertStringContainsString('SUMMARY:Wedding', $ics);
    }
}
