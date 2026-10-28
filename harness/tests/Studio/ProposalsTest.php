<?php

declare(strict_types=1);

namespace App\Tests\Studio;

use App\Studio\Proposals;
use PHPUnit\Framework\TestCase;

final class ProposalsTest extends TestCase
{
    public function testNormalisesOperationsSentAsStrings(): void
    {
        $operations = Proposals::normalise([
            '{"op":"theme.update","direction":"warm"}',
            ['op' => 'page.delete', 'page' => '/old'],
            '[{"op":"node.remove","id":"n1"},{"op":"node.remove","id":"n2"}]',
        ]);

        self::assertSame([
            ['op' => 'theme.update', 'direction' => 'warm'],
            ['op' => 'page.delete', 'page' => '/old'],
            ['op' => 'node.remove', 'id' => 'n1'],
            ['op' => 'node.remove', 'id' => 'n2'],
        ], $operations);
    }
}
