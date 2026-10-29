<?php

declare(strict_types=1);

namespace App\Tests\Agent;

use App\Agent\Schema\ListItemsToolFactory;
use PHPUnit\Framework\TestCase;

final class ListItemsToolFactoryTest extends TestCase
{
    public function testEveryArrayGetsAnItemsSchema(): void
    {
        // gpt-oss's chat template in LM Studio refuses a whole request over one array without "items".
        $schema = ListItemsToolFactory::withItems(['type' => 'object', 'properties' => [
            'operations' => ['type' => 'array', 'description' => 'Operations'],
            'ids' => ['type' => 'array', 'items' => ['type' => 'string']],
            'values' => ['type' => ['array', 'null']],
        ]]);

        self::assertEquals(new \stdClass(), $schema['properties']['operations']['items']);
        self::assertSame(['type' => 'string'], $schema['properties']['ids']['items']);
        self::assertEquals(new \stdClass(), $schema['properties']['values']['items']);
        self::assertSame('{"type":"array","description":"Operations","items":{}}', json_encode($schema['properties']['operations']));
    }
}
