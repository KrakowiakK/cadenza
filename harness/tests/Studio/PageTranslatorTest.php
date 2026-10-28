<?php

declare(strict_types=1);

namespace App\Tests\Studio;

use App\Studio\PageTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\PlatformInterface;

final class PageTranslatorTest extends TestCase
{
    private const PAGE = [
        'id' => 'pwarsz', 'path' => '/warsztaty', 'title' => 'Warsztaty', 'description' => null,
        'nodes' => [
            ['id' => 'nhero12', 'type' => 'hero', 'props' => ['title' => 'Joga i oddech', 'image' => '/media/a.jpg', 'align' => 'center'], 'slots' => [
                'actions' => [['id' => 'nbtn123', 'type' => 'button', 'props' => ['label' => 'Zapisz się', 'href' => '#nform12']]],
            ]],
            ['id' => 'nform12', 'type' => 'form', 'props' => [
                'title' => 'Zapisy', 'peopleField' => 'Ile osób', 'capacity' => 10,
                'fields' => [
                    ['label' => 'Imię', 'type' => 'text', 'required' => true],
                    ['label' => 'Ile osób', 'type' => 'number'],
                    ['label' => 'Termin', 'type' => 'radio', 'options' => ['Sobota 10:00', 'Niedziela 10:00'], 'limit' => 10],
                ],
            ]],
            ['id' => 'ngrid12', 'type' => 'collection-grid', 'props' => ['collection' => 'zajecia', 'fields' => ['cena', 'poziom'], 'title' => 'Grafik']],
        ],
    ];

    public function testOnlyWordsForPeopleAreTranslated(): void
    {
        self::assertSame(['Warsztaty', 'Joga i oddech', 'Zapisz się', 'Zapisy', 'Ile osób', 'Imię', 'Termin', 'Sobota 10:00', 'Niedziela 10:00', 'Grafik'], array_keys(PageTranslator::texts(self::PAGE)));
    }

    public function testTheSamePageIsBuiltAroundTheTranslations(): void
    {
        $translator = new PageTranslator($this->createStub(PlatformInterface::class), 'test');
        $op = $translator->translate(self::PAGE, 'en', null, static fn (array $texts): array => array_map(static fn (string $t): string => 'EN '.$t, $texts));

        self::assertSame('/en/warsztaty', $op['path']);
        self::assertSame('EN Warsztaty', $op['title']);
        self::assertSame(['lang' => 'en', 'translationOf' => 'pwarsz'], ['lang' => $op['lang'], 'translationOf' => $op['translationOf']]);

        [$hero, $form, $grid] = $op['nodes'];
        self::assertSame(['title' => 'EN Joga i oddech', 'image' => '/media/a.jpg', 'align' => 'center'], $hero['props']);
        self::assertSame('nhero12-en', $hero['id']);
        self::assertSame(['label' => 'EN Zapisz się', 'href' => '#nform12-en'], $hero['actions'][0]['props']);
        self::assertSame('nform12-en', $form['id']);
        self::assertSame('EN Ile osób', $form['props']['peopleField'], 'the people field still names its (translated) field');
        self::assertSame(['label' => 'EN Termin', 'type' => 'radio', 'options' => ['EN Sobota 10:00', 'EN Niedziela 10:00'], 'limit' => 10], $form['props']['fields'][2]);
        self::assertSame(['collection' => 'zajecia', 'fields' => ['cena', 'poziom'], 'title' => 'EN Grafik'], $grid['props']);
    }

    public function testTheMenuFollowsTheTranslatedPagesAndSections(): void
    {
        $translator = new PageTranslator($this->createStub(PlatformInterface::class), 'test');
        $en = static fn (array $texts): array => array_map(static fn (string $t): string => 'EN '.$t, $texts);

        self::assertNull($translator->translateNav(null, 'en', ['/'], $en), 'an automatic menu needs nothing');
        self::assertSame(['op' => 'site.update', 'lang' => 'en', 'nav' => [
            ['label' => 'EN O mnie', 'href' => '#nabout1-en'],
            ['label' => 'EN Warsztaty', 'href' => '/en/warsztaty#nform12-en'],
            ['label' => 'EN Kontakt', 'href' => '/kontakt'],
        ]], $translator->translateNav([
            ['label' => 'O mnie', 'href' => '#nabout1'],
            ['label' => 'Warsztaty', 'href' => '/warsztaty#nform12'],
            ['label' => 'Kontakt', 'href' => '/kontakt'],
        ], 'en', ['/', '/warsztaty'], $en));
    }
}
