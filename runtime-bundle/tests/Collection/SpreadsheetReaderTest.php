<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Tests\Collection;

use Cadenza\Runtime\Collection\SpreadsheetReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpreadsheetReaderTest extends TestCase
{
    /**
     * @param list<string> $values
     */
    #[DataProvider('columns')]
    public function testGuessesColumnTypes(string $header, array $values, string $expected): void
    {
        self::assertSame($expected, SpreadsheetReader::guessType($header, $values));
    }

    /**
     * @return iterable<string, array{string, list<string>, string}>
     */
    public static function columns(): iterable
    {
        yield 'price' => ['Cena', ['89 zł', '129 zł', '39 zł'], 'price'];
        yield 'number' => ['Waga', ['1,5', '2', '10'], 'number'];
        yield 'category' => ['Kategoria', ['Kubki', 'Miski', 'Kubki', 'Miski', 'Talerze', 'Kubki', 'Miski', 'Talerze'], 'category'];
        yield 'image' => ['Zdjęcie', ['https://example.com/a.jpg', 'https://example.com/b.png'], 'image'];
        yield 'url' => ['Link', ['https://example.com', 'https://symfony.com'], 'url'];
        yield 'email' => ['E-mail', ['a@example.com', 'b@example.com'], 'email'];
        yield 'bool' => ['Dostępny', ['Tak', 'Nie', 'Tak'], 'bool'];
        yield 'date' => ['Data', ['2024-07-12', '2024-08-01'], 'date'];
        yield 'text' => ['Nazwa', ['Kubek Poranek', 'Miska Bałtyk', 'Talerz Len'], 'text'];
    }

    public function testNormalisesValues(): void
    {
        self::assertSame(1299.5, SpreadsheetReader::normalise('price', '1 299,50 zł'));
        self::assertTrue(SpreadsheetReader::normalise('bool', 'Tak'));
        self::assertNull(SpreadsheetReader::normalise('text', '  '));
    }

    public function testReadsASheetAndSkipsTheTotalRow(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sheet').'.csv';
        file_put_contents($path, "Nazwa,Kategoria,Cena\nKubek,Kubki,89 zł\nMiska,Miski,129 zł\nSuma,,218 zł\n");
        $sheets = (new SpreadsheetReader())->read($path);
        unlink($path);

        self::assertCount(2, $sheets[0]['rows']);
        self::assertSame(['nazwa', 'kategoria', 'cena'], array_column($sheets[0]['columns'], 'key'));
        self::assertSame('price', $sheets[0]['columns'][2]['type']);
    }
}
