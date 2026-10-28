<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Tests\Site;

use Cadenza\Runtime\Catalog\Catalog;
use Cadenza\Runtime\Catalog\CoreNodeTypes;
use Cadenza\Runtime\Operation\OperationApplier;
use Cadenza\Runtime\Site\Diff;
use Cadenza\Runtime\Site\Document;
use PHPUnit\Framework\TestCase;

final class DiffTest extends TestCase
{
    public function testFindsAddedChangedAndRemovedNodes(): void
    {
        $applier = new OperationApplier(new Catalog([new CoreNodeTypes()]));
        $base = $applier->apply(Document::empty(), [['op' => 'page.create', 'path' => '/', 'title' => 'Home', 'nodes' => [
            ['type' => 'heading', 'props' => ['text' => 'Keep']],
            ['type' => 'heading', 'props' => ['text' => 'Change']],
            ['type' => 'heading', 'props' => ['text' => 'Remove']],
        ]]])->document;
        [$keep, $change, $remove] = array_column($base['pages'][0]['nodes'], 'id');

        $draft = $applier->apply($base, [
            ['op' => 'node.update', 'id' => $change, 'props' => ['text' => 'Changed']],
            ['op' => 'node.remove', 'id' => $remove],
            ['op' => 'node.insert', 'page' => '/', 'node' => ['type' => 'divider']],
        ])->document;

        $diff = Diff::between($base, $draft);
        self::assertSame('changed', $diff->nodes[$change]);
        self::assertArrayNotHasKey($keep, $diff->nodes);
        self::assertArrayHasKey($remove, $diff->removed);
        self::assertSame(['added' => 1, 'changed' => 1, 'removed' => 1], $diff->summary());
        self::assertSame('/', $diff->focusPath($draft));
    }

    public function testMovingABlockIsAChangeOfThatBlockOnly(): void
    {
        $applier = new OperationApplier(new Catalog([new CoreNodeTypes()]));
        $base = $applier->apply(Document::empty(), [['op' => 'page.create', 'path' => '/', 'title' => 'Home', 'nodes' => array_map(
            static fn (string $t): array => ['type' => 'heading', 'props' => ['text' => $t]], ['Hero', 'About', 'Menu', 'Course', 'Pricing', 'Contact'],
        )]])->document;
        $ids = array_column($base['pages'][0]['nodes'], 'id');

        $draft = $applier->apply($base, [['op' => 'node.move', 'id' => $ids[4], 'after' => $ids[0]]])->document;
        self::assertSame([$ids[4] => 'changed'], Diff::between($base, $draft)->nodes, 'only the pricing moved, not the blocks it jumped over');

        $draft = $applier->apply($base, [['op' => 'node.move', 'id' => $ids[0], 'page' => '/', 'position' => 2]])->document;
        self::assertSame($ids[0], $draft['pages'][0]['nodes'][2]['id'], '"position" works as "index"');
        self::assertCount(1, Diff::between($base, $draft)->nodes);
    }

    public function testNoChangesIsEmpty(): void
    {
        $doc = Document::empty();
        self::assertTrue(Diff::between($doc, $doc)->isEmpty());
    }
}
