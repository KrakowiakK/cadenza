<?php

declare(strict_types=1);

namespace App\Tests\Agent;

use App\Agent\JsonRepair;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JsonRepairTest extends TestCase
{
    #[DataProvider('damaged')]
    public function testRepairsWhatModelsSend(string $json, mixed $expected): void
    {
        self::assertSame($expected, JsonRepair::decode($json));
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function damaged(): iterable
    {
        yield 'valid' => ['[{"op":"a"}]', [['op' => 'a']]];
        yield 'markdown fence' => ["```json\n[{\"op\":\"a\"}]\n```", [['op' => 'a']]];
        yield 'extra closing brackets' => ['[{"op":"a","nodes":[{"x":1}]}]}]', [['op' => 'a', 'nodes' => [['x' => 1]]]]];
        yield 'escaped once more' => ['[{\"op\":\"a\"}]', [['op' => 'a']]];
        yield 'json inside a json string' => ['"[{\"op\":\"a\"}]"', [['op' => 'a']]];
        yield 'brackets inside strings' => ['{"text":"a ] b }"} trailing', ['text' => 'a ] b }']];
        yield 'trailing commas' => ['[{"op":"a","nodes":[{"x":1},]},]', [['op' => 'a', 'nodes' => [['x' => 1]]]]];
        yield 'cut off before the end' => ['[{"op":"a","nodes":[{"text":"Co zabrać"}', [['op' => 'a', 'nodes' => [['text' => 'Co zabrać']]]]];
        yield 'an item not closed in the middle' => ['[{"op":"a","nodes":[{"type":"steps","props":{"x":1}, {"type":"cta","props":{"y":2}}]}]',
            [['op' => 'a', 'nodes' => [['type' => 'steps', 'props' => ['x' => 1]], ['type' => 'cta', 'props' => ['y' => 2]]]]]];
        yield 'an object closed too early' => ['[{"id":"a","op":"node.remove"},{"page":"/gry","node":{"widget":"snake"},"type":"widget"},"op":"node.insert"}]',
            [['id' => 'a', 'op' => 'node.remove'], ['page' => '/gry', 'node' => ['widget' => 'snake'], 'type' => 'widget', 'op' => 'node.insert']]];
        yield 'hopeless' => ['not json at all', null];
    }
}
