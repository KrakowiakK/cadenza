<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Tests\Media;

use Cadenza\Runtime\Media\MapPoints;
use PHPUnit\Framework\TestCase;

final class MapPointsTest extends TestCase
{
    public function testAddressGetsSimplerUntilSomethingIsFound(): void
    {
        self::assertSame([
            'Dwór Łoś, ul. Krakowska 59, 24-120 Kazimierz Dolny, Polska',
            'Dwór Łoś, Krakowska 59, Kazimierz Dolny, Polska',
            'Krakowska 59, Kazimierz Dolny, Polska',
            'Kazimierz Dolny, Polska',
        ], MapPoints::candidates('Dwór Łoś, ul. Krakowska 59, 24-120 Kazimierz Dolny, Polska'));
    }
}
