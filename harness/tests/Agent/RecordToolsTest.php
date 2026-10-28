<?php

declare(strict_types=1);

namespace App\Tests\Agent;

use App\Agent\Tool\RecordTools;
use PHPUnit\Framework\TestCase;

final class RecordToolsTest extends TestCase
{
    public function testFieldValuesInTheShapesModelsSend(): void
    {
        $expected = ['tytul' => 'Autobus', 'data' => '2027-06-14'];

        self::assertSame($expected, RecordTools::fieldValues($expected));
        self::assertSame($expected, RecordTools::fieldValues([$expected]));
        self::assertSame($expected, RecordTools::fieldValues([['field' => 'tytul', 'value' => 'Autobus'], ['key' => 'data', 'value' => '2027-06-14']]));
        self::assertSame($expected, RecordTools::fieldValues(['tytul: Autobus', 'data = 2027-06-14']));
        self::assertSame([], RecordTools::fieldValues([]));
    }
}
