<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Cadenza\Runtime\Collection\Collections;
use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Entity\Account;
use Cadenza\Runtime\Site\Document;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Entries written by chat are drafts: the owner sees them marked, visitors see the live version until the owner
 * publishes. Markdown in entries cannot smuggle scripts.
 */
final class RecordDraftsTest extends WebTestCase
{
    public function testNewsPostsAndPriceChangesWaitForTheOwner(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        $site = $container->get(SiteManager::class);
        $site->initialise(Document::empty('Café'), 'Blank');

        $collections = $container->get(Collections::class);
        $news = $collections->create('aktualnosci', 'Aktualności', [['label' => 'Tytuł'], ['label' => 'Data', 'type' => 'date'], ['label' => 'Treść', 'type' => 'longtext']]);
        $result = $site->propose([
            ['op' => 'site.update', 'language' => 'pl'],
            ['op' => 'page.create', 'path' => '/', 'title' => 'Café', 'nodes' => [['type' => 'collection-grid', 'props' => ['collection' => 'aktualnosci', 'style' => 'list', 'sort' => 'data', 'order' => 'desc']]]],
        ], 'News');
        $site->publish((int) $result->draft->getId());

        $old = $collections->save($news, ['tytul' => 'Zimowe godziny', 'data' => '2026-12-01', 'tresc' => 'Od grudnia do 18.']);
        $collections->decide($news, true);
        $post = $collections->save($news, ['Tytuł' => 'Otwieramy ogródek', 'Data' => '2027-05-01', 'Treść' => "## Od maja\n\nZapraszamy **na taras**. <script>alert(1)</script> [x](javascript:alert(2))"]);
        self::assertSame('new', $post->pending());

        // A visitor: only the published post.
        $client->request('GET', '/');
        self::assertSelectorTextContains('main', 'Zimowe godziny');
        self::assertSelectorTextNotContains('main', 'Otwieramy ogródek');
        $client->request('GET', '/aktualnosci/'.$post->getSlug());
        self::assertResponseStatusCodeSame(404);

        // The owner: the draft too, marked, newest first.
        $owner = new Account('owner@example.com', [Account::OWNER]);
        $em->persist($owner);
        $em->flush();
        $client->loginUser($owner);
        $crawler = $client->request('GET', '/');
        self::assertSame(['Otwieramy ogródek', 'Zimowe godziny'], $crawler->filter('main h3')->each(static fn ($h): string => trim($h->text())));
        self::assertSelectorExists('.cz-record-pending');
        $client->request('GET', '/aktualnosci/'.$post->getSlug());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.cz-article h2', 'Od maja');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringNotContainsString('javascript:alert(2)', $html);

        // Changing a published post keeps the live version until approval.
        $collections->save($news, ['tytul' => 'Zimowe godziny (do 17)'], $old->getSlug());
        $client->request('GET', '/?cadenza_guest=1');
        self::assertSelectorTextContains('main', 'Zimowe godziny');
        self::assertSelectorTextNotContains('main', '(do 17)');

        self::assertSame(2, $collections->decide($news, true));
        $client->request('GET', '/?cadenza_guest=1');
        self::assertSelectorTextContains('main', 'Otwieramy ogródek');
        self::assertSelectorTextContains('main', 'Zimowe godziny (do 17)');
        self::assertSelectorNotExists('.cz-record-pending');
    }
}
