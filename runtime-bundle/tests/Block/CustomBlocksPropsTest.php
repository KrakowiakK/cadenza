<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Tests\Block;

use Cadenza\Runtime\Block\CustomBlocks;
use PHPUnit\Framework\TestCase;

final class CustomBlocksPropsTest extends TestCase
{
    public function testListDialectsBecomeOneSchema(): void
    {
        [$props, $errors] = CustomBlocks::normaliseProps([
            'title' => 'string',
            'weeks' => ['type' => 'list', 'of' => ['week' => ['type' => 'int', 'required' => true], 'learnings' => ['type' => 'list', 'items' => ['type' => 'string']]]],
            'tags' => ['type' => 'list'],
        ]);

        self::assertSame([], $errors);
        self::assertSame(['type' => 'string'], $props['title']);
        self::assertSame(['type' => 'int', 'required' => true], $props['weeks']['items']['week']);
        self::assertSame(['type' => 'list', 'of' => 'string'], $props['weeks']['items']['learnings']);
        self::assertSame(['type' => 'list', 'of' => 'string'], $props['tags']);
    }

    public function testUnknownTypesAreExplained(): void
    {
        [, $errors] = CustomBlocks::normaliseProps(['x' => ['type' => 'object'], 'y' => ['type' => 'enum']]);

        self::assertCount(2, $errors);
        self::assertStringContainsString('type is one of', $errors[0]);
        self::assertStringContainsString('needs "values"', $errors[1]);
    }
}
