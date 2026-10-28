<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Site\Document;
use Cadenza\Runtime\Stats\SiteStats;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Visitors are counted (not the owner, not bots), and only the owner sees the dashboard.
 */
final class DashboardTest extends WebTestCase
{
    public function testVisitsAreCountedAndTheOwnerSeesThem(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        $site = $container->get(SiteManager::class);
        $site->initialise(Document::empty('Bakery'), 'Blank');
        $result = $site->propose([
            ['op' => 'page.create', 'path' => '/', 'title' => 'Home', 'nodes' => [['type' => 'heading', 'props' => ['text' => 'Fresh bread']]]],
            ['op' => 'page.create', 'path' => '/menu', 'title' => 'Menu', 'nodes' => [['type' => 'heading', 'props' => ['text' => 'Menu']]]],
        ], 'Pages');
        $site->publish((int) $result->draft->getId());

        $visitor = ['HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone)'];
        $client->request('GET', '/?cadenza_guest=1', [], [], $visitor);
        $client->request('GET', '/?cadenza_guest=1', [], [], $visitor);
        $client->request('GET', '/menu?cadenza_guest=1', [], [], $visitor);
        $client->request('GET', '/menu?cadenza_guest=1', [], [], ['HTTP_USER_AGENT' => 'Googlebot/2.1']);
        $client->request('GET', '/menu?cadenza_guest=1', [], [], $visitor + ['HTTP_SEC_PURPOSE' => 'prefetch']);
        $client->request('GET', '/menu?cadenza_owner='.$container->getParameter('cadenza.token'), [], [], $visitor);

        $visits = static::getContainer()->get(SiteStats::class)->visits(30);
        self::assertSame(3, $visits['today']);
        self::assertSame([['path' => '/', 'views' => 2], ['path' => '/menu', 'views' => 1]], $visits['pages']);
        self::assertCount(30, $visits['days']);

        $client->request('GET', '/_cadenza/dashboard?cadenza_guest=1');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/_cadenza/dashboard?cadenza_owner='.$container->getParameter('cadenza.token'));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-cz-chart] svg');
        self::assertSelectorNotExists('.cz-node-error');
    }
}
