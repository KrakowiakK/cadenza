<?php

declare(strict_types=1);

namespace App\Tests\Studio;

use App\Studio\Conductor;
use PHPUnit\Framework\TestCase;

final class ConductorTest extends TestCase
{
    public function testFindsForeignWordsInPolishReplies(): void
    {
        self::assertSame(['weitere'], Conductor::foreignWords('Możesz poprosić o weitere zmiany.', 'pl'));
        self::assertSame(['живого'], Conductor::foreignWords('**Coś живого** – gry i animacje.', 'pl'));
        self::assertSame(['draft', 'create_widget'], Conductor::foreignWords('Twój draft jest gotowy, użyłam create_widget.', 'pl'));
        self::assertSame([], Conductor::foreignWords('Szkic jest gotowy: widżet na stronie /gry, plik site.webmanifest zostaje.', 'pl'));
        self::assertSame([], Conductor::foreignWords('The draft is ready.', 'en'));
        self::assertSame(['School'], Conductor::foreignWords('Powiedz, jeśli chceszSchool ją przywrócić.', 'pl'));
        self::assertSame(['`tetris-game`'], Conductor::foreignWords('Widżet `tetris-game` działa na `/gry`.', 'pl'));
        self::assertSame('Gotowe — jest w szkicu.', Conductor::withoutModelTokens('Gotowe — jest w szkicu.tool_calls_section_end|<|im_end|>'));
    }
}
