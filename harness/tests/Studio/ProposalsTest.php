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

    public function testSummariesLoseMarkupLeftovers(): void
    {
        self::assertSame('Nowa podstrona „Nasze podróże”.', Proposals::cleanSummary("Nowa podstrona „Nasze podróże”.</"));
        self::assertSame('Ceny < 50 zł bez zmian', Proposals::cleanSummary('<b>Ceny < 50 zł</b> bez  zmian'));
        self::assertSame('Changes', Proposals::cleanSummary(' </p> '));
        self::assertSame('"EN" version of /gry', Proposals::cleanSummary('"EN" version of /gry'));
        self::assertSame('Wersja "EN"', Proposals::cleanSummary('Wersja "EN"'));
        self::assertSame('Formularz zapisów, do 12 osób', Proposals::cleanSummary('Formularz zapisów, do 12 osób">'));
        self::assertSame('Cały cytat', Proposals::cleanSummary('"Cały cytat"'));
        self::assertSame('Zapisy na trzy warsztaty listopadowe', Proposals::cleanSummary('Zapisy na trzy warsztaty listopadowe]]'));
    }
}
