<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Site\Document;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A page in a second language: its own navigation, the switcher leads to the same page in the other language, and
 * texts of the site itself (forms) follow the page's language.
 */
final class TranslatedPagesTest extends WebTestCase
{
    public function testEnglishVersionOfPolishPages(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        $site = $container->get(SiteManager::class);
        $site->initialise(Document::empty('Oddech'), 'Blank');
        $result = $site->propose([
            ['op' => 'site.update', 'language' => 'pl'],
            ['op' => 'page.create', 'id' => 'phome', 'path' => '/', 'title' => 'Start', 'nodes' => [['type' => 'heading', 'props' => ['text' => 'Witaj']]]],
            ['op' => 'page.create', 'id' => 'pwork', 'path' => '/warsztaty', 'title' => 'Warsztaty', 'nodes' => [['id' => 'nform12', 'type' => 'form', 'props' => ['fields' => [['label' => 'Imię', 'type' => 'text']]]]]],
            ['op' => 'page.create', 'path' => '/en', 'title' => 'Home', 'lang' => 'en', 'translationOf' => '/', 'nodes' => [['type' => 'heading', 'props' => ['text' => 'Welcome']]]],
            ['op' => 'page.create', 'path' => '/en/warsztaty', 'title' => 'Workshops', 'lang' => 'en', 'translationOf' => 'pwork', 'nodes' => [['id' => 'nform12-en', 'type' => 'form', 'props' => ['fields' => [['label' => 'Name', 'type' => 'text']]]]]],
        ], 'Two languages');
        self::assertTrue($result->isValid(), implode("\n", $result->errors));
        $site->publish((int) $result->draft->getId());

        $crawler = $client->request('GET', '/warsztaty');
        self::assertSame('pl', $crawler->filter('body')->attr('lang'));
        self::assertSame(['Start', 'Warsztaty'], $crawler->filter('header nav a')->each(static fn ($a): string => trim($a->text())));
        self::assertSame('/en/warsztaty', $crawler->filter('a[hreflang="en"]')->attr('href'));
        self::assertSelectorTextContains('[data-cz-form] [type=submit]', 'Wyślij');

        $crawler = $client->request('GET', '/en/warsztaty');
        self::assertSame('en', $crawler->filter('body')->attr('lang'));
        self::assertSame(['Home', 'Workshops'], $crawler->filter('header nav a')->each(static fn ($a): string => trim($a->text())));
        self::assertSame('/warsztaty', $crawler->filter('a[hreflang="pl"]')->attr('href'));
        self::assertSelectorTextContains('[data-cz-form] [type=submit]', 'Send');
        self::assertSelectorExists('#nform12-en');
    }
}
