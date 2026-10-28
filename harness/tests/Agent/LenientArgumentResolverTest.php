<?php

declare(strict_types=1);

namespace App\Tests\Agent;

use App\Agent\LenientArgumentResolver;
use PHPUnit\Framework\TestCase;

final class LenientArgumentResolverTest extends TestCase
{
    public function testJsonEscapesLeftInTextsBecomeLetters(): void
    {
        self::assertSame('Pong dla dwóch osób', LenientArgumentResolver::unescapeUnicode('Pong dla dw\u00f3ch os\u00f3b'));
        self::assertSame('Uśmiech 😀', LenientArgumentResolver::unescapeUnicode('Uśmiech \ud83d\ude00'));
        self::assertSame('C:\users', LenientArgumentResolver::unescapeUnicode('C:\users'));
    }
}
