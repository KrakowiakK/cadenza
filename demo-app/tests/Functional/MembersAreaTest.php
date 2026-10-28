<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Cadenza\Runtime\Account\Members;
use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Entity\Account;
use Cadenza\Runtime\Site\Document;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A members-only page: closed to strangers (without saying what is behind it), open to an invited guest who signs in
 * with their personal link, closed again for a guest the owner removed.
 */
final class MembersAreaTest extends WebTestCase
{
    public function testInvitedGuestsSeeTheMembersPage(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        $site = $container->get(SiteManager::class);
        $site->initialise(Document::empty('Wedding'), 'Blank');
        $result = $site->propose([
            ['op' => 'site.update', 'language' => 'pl'],
            ['op' => 'page.create', 'path' => '/', 'title' => 'Ślub', 'nodes' => [['type' => 'heading', 'props' => ['text' => 'Zapraszamy']]]],
            ['op' => 'page.create', 'path' => '/nocleg', 'title' => 'Nocleg', 'access' => 'members', 'nodes' => [['type' => 'heading', 'props' => ['text' => 'Kod do furtki 4711']]]],
        ], 'Pages');
        self::assertTrue($result->isValid(), implode("\n", $result->errors));
        $site->publish((int) $result->draft->getId());

        $client->request('GET', '/');
        self::assertSelectorExists('nav a[href="/nocleg"] svg', 'the members page is marked in the navigation');

        $client->request('GET', '/nocleg');
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('h1', 'tylko dla zaproszonych');
        self::assertStringNotContainsString('4711', (string) $client->getResponse()->getContent());

        // Asking for a link with an unknown address looks exactly like asking with a known one.
        $client->submitForm('Wyślij mi link', ['email' => 'stranger@example.com']);
        self::assertResponseRedirects('/nocleg?cz_link=sent');

        $members = $container->get(Members::class);
        $guest = $members->add('kasia@example.com', 'Kasia Nowak');
        $link = $members->link($guest, Members::INVITE_LIFETIME, '/nocleg');

        $client->request('GET', $link);
        self::assertResponseRedirects('/nocleg');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Kod do furtki 4711');
        self::assertSelectorTextContains('footer', 'Kasia Nowak');
        self::assertSelectorNotExists('#cz-panel', 'a member is not the owner');
        $client->request('GET', '/_cadenza/dashboard');
        self::assertResponseStatusCodeSame(404);

        // Removed by the owner: the old link and the session no longer open the page.
        $members->remove($members->byEmail('kasia@example.com'));
        $client->request('GET', '/nocleg');
        self::assertResponseStatusCodeSame(403);

        // The owner sees the page, and the guest list on the dashboard.
        $owner = new Account('owner@example.com', [Account::OWNER]);
        $em->persist($owner);
        $em->flush();
        $client->loginUser($owner);
        $client->request('GET', '/nocleg');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/_cadenza/dashboard');
        self::assertSelectorTextContains('#members', 'Nocleg');
    }
}
