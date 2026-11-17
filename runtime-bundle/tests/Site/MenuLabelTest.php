<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Tests\Site;

use Cadenza\Runtime\Site\Document;
use PHPUnit\Framework\TestCase;

final class MenuLabelTest extends TestCase
{
    public function testTheMenuNamesPagesWithoutTheNameOfTheSite(): void
    {
        $doc = Document::empty();
        $doc['name'] = 'Tidewater Yoga';
        $doc['language'] = 'en';
        $doc['pages'] = [
            ['id' => 'a', 'path' => '/', 'title' => 'Tidewater Yoga — yoga in Gdynia', 'nodes' => []],
            ['id' => 'b', 'path' => '/classes', 'title' => 'Weekly classes — Tidewater Yoga', 'nodes' => []],
            ['id' => 'c', 'path' => '/news', 'title' => 'News | Tidewater Yoga', 'nodes' => []],
            ['id' => 'd', 'path' => '/about', 'title' => 'About', 'nodes' => []],
            ['id' => 'e', 'path' => '/prices', 'title' => 'Prices', 'navLabel' => 'What it costs', 'nodes' => []],
            ['id' => 'f', 'path' => '/drop-in', 'title' => 'Drop-in classes', 'nodes' => []],
        ];

        self::assertSame(['Home', 'Weekly classes', 'News', 'About', 'What it costs', 'Drop-in classes'], array_column(Document::navItems($doc), 'label'));
    }

    public function testAHomePageCalledHomeStaysAsWritten(): void
    {
        $doc = Document::empty();
        $doc['name'] = 'My site';
        $doc['pages'] = [['id' => 'a', 'path' => '/', 'title' => 'Welcome', 'nodes' => []]];

        self::assertSame(['Welcome'], array_column(Document::navItems($doc), 'label'));
    }
}
