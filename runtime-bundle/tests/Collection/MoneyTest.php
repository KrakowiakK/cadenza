<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Tests\Collection;

use Cadenza\Runtime\Collection\Collections;
use Cadenza\Runtime\Collection\SpreadsheetReader;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testAPriceKeepsTheCurrencyTheOwnerWroteItIn(): void
    {
        self::assertSame('price', SpreadsheetReader::guessType('Price', ['£4.80', '£5.20', '£12']));
        $pounds = ['money' => SpreadsheetReader::moneyOf(['£4.80', '£5.20'])];
        self::assertSame('£5.20', Collections::format('price', 5.2, $pounds, 'en'));
        self::assertSame('£12', Collections::format('price', 12.0, $pounds, 'en'));

        $euro = ['money' => SpreadsheetReader::moneyOf(['4,80 €', '12 €'])];
        self::assertSame('4,80 €', Collections::format('price', 4.8, $euro, 'en'));

        $zloty = ['money' => SpreadsheetReader::moneyOf(['89 zł', '1 715 zł'])];
        self::assertSame('1 715 zł', Collections::format('price', 1715.0, $zloty, 'en'));
    }

    public function testWithoutACurrencyAPolishSiteSaysZlotyAndOthersSayTheNumber(): void
    {
        self::assertNull(SpreadsheetReader::moneyOf(['12', '14.50']));
        self::assertSame('14,50 zł', Collections::format('price', 14.5, null, 'pl'));
        self::assertSame('14.50', Collections::format('price', 14.5, null, 'en'));
    }
}
