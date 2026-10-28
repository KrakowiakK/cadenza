<?php

declare(strict_types=1);

namespace App\Tests\Agent;

use App\Agent\Tool\SiteTools;
use App\Agent\Tool\WidgetTools;
use PHPUnit\Framework\TestCase;

final class WidgetToolsTest extends TestCase
{
    public function testASummaryWhenTheModelGaveNone(): void
    {
        self::assertSame('Nowa strona „Dla firm”, Nowa sekcja', SiteTools::summaryOf([['op' => 'page.create', 'path' => '/dla-firm', 'title' => 'Dla firm'], ['op' => 'node.insert'], ['op' => 'node.insert']], true));
        self::assertSame('Sections reordered', SiteTools::summaryOf([['op' => 'node.move']], false));
    }

    public function testNamesModelsGiveBecomeValidOnes(): void
    {
        self::assertSame('pogoda-w-krakowie', WidgetTools::slug('Pogoda w Krakowie'));
        self::assertSame('drzewo-rosnace-przy-przewijaniu', WidgetTools::slug('Drzewo rosnące przy przewijaniu'));
        self::assertSame('zlap-kamyki', WidgetTools::slug('Złap kamyki'));
        self::assertSame('snake-game', WidgetTools::slug('snake-game'));
        self::assertSame('w-2048', WidgetTools::slug('2048'));
        self::assertLessThanOrEqual(38, \strlen(WidgetTools::slug(str_repeat('Bardzo długa nazwa ', 5))));
    }
}
