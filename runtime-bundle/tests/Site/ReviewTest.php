<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Tests\Site;

use Cadenza\Runtime\Site\Review;
use PHPUnit\Framework\TestCase;

final class ReviewTest extends TestCase
{
    public function testItFindsWhatAVisitorWouldStumbleOn(): void
    {
        $findings = Review::of([
            'name' => 'My site',
            'pages' => [
                ['id' => 'p1', 'path' => '/', 'title' => 'Home', 'description' => null, 'nodes' => [
                    ['id' => 'nhero01', 'type' => 'hero', 'props' => ['title' => 'Hello', 'buttonHref' => '/prices'], 'slots' => ['actions' => [
                        ['id' => 'nbtn001', 'type' => 'button', 'props' => ['label' => 'Classes', 'href' => '/classes#nlist01']],
                        ['id' => 'nbtn002', 'type' => 'button', 'props' => ['label' => 'More', 'href' => '#nowhere']],
                        ['id' => 'nbtn003', 'type' => 'button', 'props' => ['label' => 'Shop', 'href' => '/vases/blue-vase']],
                        ['id' => 'nbtn004', 'type' => 'button', 'props' => ['label' => 'Elsewhere', 'href' => 'https://example.com']],
                    ]]],
                    ['id' => 'nbento1', 'type' => 'bento', 'props' => ['items' => [['title' => 'Timetable', 'href' => '#'], ['title' => 'Lorem ipsum dolor sit amet']]]],
                    ['id' => 'nstub01', 'type' => 'stub', 'props' => ['label' => 'Opening hours']],
                ]],
                ['id' => 'p2', 'path' => '/classes', 'title' => 'Classes', 'description' => 'Weekly classes.', 'nodes' => [
                    ['id' => 'nlist01', 'type' => 'text', 'props' => ['text' => 'Monday yoga']],
                ]],
            ],
        ], ['vases']);
        $kinds = array_count_values(array_column($findings, 'kind'));

        self::assertSame(['name' => 1, 'description' => 1, 'link' => 3, 'filler' => 1, 'placeholder' => 1, 'contact' => 1], $kinds);
        $links = implode(' | ', array_column(array_filter($findings, static fn (array $f): bool => 'link' === $f['kind']), 'problem'));
        self::assertStringContainsString('"/prices"', $links);
        self::assertStringContainsString('"#nowhere"', $links);
        self::assertStringContainsString('leads nowhere', $links);
        self::assertStringNotContainsString('/classes#nlist01', $links, 'a page and a section that exist');
        self::assertStringNotContainsString('blue-vase', $links, 'records of a collection have pages');
    }

    public function testAFinishedSiteHasNoFindings(): void
    {
        self::assertSame([], Review::of(['name' => 'Hearth', 'pages' => [
            ['id' => 'p1', 'path' => '/', 'title' => 'Home', 'description' => 'A bakery.', 'nodes' => [
                ['id' => 'nform01', 'type' => 'form', 'props' => ['title' => 'Write to us']],
            ]],
        ]]));
    }
}
