<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Tests\Site;

use Cadenza\Runtime\Site\LinkRepair;
use PHPUnit\Framework\TestCase;

final class LinkRepairTest extends TestCase
{
    public function testAButtonNeverLeadsNowhere(): void
    {
        $operations = LinkRepair::operations(['pages' => [['id' => 'p1', 'path' => '/', 'nodes' => [
            ['id' => 'nhero01', 'type' => 'hero', 'props' => ['title' => 'Yoga'], 'slots' => ['actions' => [
                ['id' => 'nbtn001', 'type' => 'button', 'props' => ['label' => 'Get in touch', 'href' => '#']],
                ['id' => 'nbtn002', 'type' => 'button', 'props' => ['label' => 'About the studio', 'href' => '']],
                ['id' => 'nbtn003', 'type' => 'button', 'props' => ['label' => 'Classes', 'href' => '/classes']],
            ]]],
            ['id' => 'nabout1', 'type' => 'feature-split', 'props' => ['title' => 'The studio', 'buttonLabel' => 'Say hello', 'buttonHref' => '#']],
            ['id' => 'nbento1', 'type' => 'bento', 'props' => ['items' => [['title' => 'Prices', 'href' => '#'], ['title' => 'Find us', 'href' => '/visit']]]],
            ['id' => 'nform01', 'type' => 'form', 'props' => ['title' => 'Write to us']],
            ['id' => 'ncta001', 'type' => 'cta-band', 'props' => ['title' => 'Come', 'buttonLabel' => 'Read more', 'buttonHref' => '#', 'secondaryHref' => '#']],
        ]]]]);

        self::assertSame([
            ['op' => 'node.update', 'id' => 'nbtn001', 'props' => ['href' => '#nform01']],
            ['op' => 'node.update', 'id' => 'nbtn002', 'props' => ['href' => '#nabout1']],
            ['op' => 'node.update', 'id' => 'nabout1', 'props' => ['buttonHref' => '#nform01']],
            ['op' => 'node.update', 'id' => 'nbento1', 'props' => ['items' => [['title' => 'Prices'], ['title' => 'Find us', 'href' => '/visit']]]],
            ['op' => 'node.update', 'id' => 'ncta001', 'props' => ['buttonHref' => '#nform01']],
        ], $operations, 'contact-like labels go to the form, others to the next section, tiles stop being links; a link without a label is left alone');
    }

    public function testNothingToRepair(): void
    {
        self::assertSame([], LinkRepair::operations(['pages' => [['id' => 'p1', 'path' => '/', 'nodes' => [
            ['id' => 'nhero01', 'type' => 'hero', 'props' => ['title' => 'Yoga', 'buttonLabel' => 'More', 'buttonHref' => '/about']],
        ]]]]));
    }
}
