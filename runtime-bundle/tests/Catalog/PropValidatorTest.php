<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Tests\Catalog;

use Cadenza\Runtime\Catalog\Catalog;
use Cadenza\Runtime\Catalog\CoreNodeTypes;
use Cadenza\Runtime\Catalog\PatternNodeTypes;
use Cadenza\Runtime\Catalog\PropValidator;
use PHPUnit\Framework\TestCase;

final class PropValidatorTest extends TestCase
{
    public function testValidatesNestedListsOfObjects(): void
    {
        $menu = (new Catalog([new PatternNodeTypes()]))->get('menu');
        [$props, $errors] = (new PropValidator())->validate($menu, ['categories' => [
            ['name' => 'Kawa', 'items' => [['name' => 'Espresso', 'price' => '9 zł']]],
        ]], true);

        self::assertSame([], $errors);
        self::assertSame('Espresso', $props['categories'][0]['items'][0]['name']);

        [, $errors] = (new PropValidator())->validate($menu, ['categories' => [['items' => []]]], true);
        self::assertStringContainsString('categories[0].name is required', $errors[0]);
    }

    public function testEveryNodeTypeHasATemplate(): void
    {
        $catalog = new Catalog([new CoreNodeTypes(), new PatternNodeTypes()]);
        foreach ($catalog->all() as $type) {
            $template = \dirname(__DIR__, 2).'/templates/node/'.$type->name.'.html.twig';
            self::assertFileExists($template, $type->name);
        }
    }
}
