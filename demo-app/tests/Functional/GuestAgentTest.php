<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\EventSubscriber\McpGuardSubscriber;
use Cadenza\Runtime\Mcp\Guest\GuestTools;
use Cadenza\Runtime\Repository\SiteVersionRepository;
use Cadenza\Runtime\Site\Document;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * An agent the owner brings along over MCP: its token opens one door, it leaves sketches, and it never publishes.
 */
final class GuestAgentTest extends WebTestCase
{
    public function testAGuestReadsAndSketchesAndNothingElse(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        $site = $container->get(SiteManager::class);
        $document = Document::empty('Hearth');
        $document['pages'][] = ['id' => 'phome01', 'path' => '/', 'title' => 'Hearth', 'nodes' => [
            ['id' => 'nhero01', 'type' => 'hero', 'props' => ['title' => 'Slow bread']],
            ['id' => 'nsect01', 'type' => 'section', 'props' => [], 'slots' => ['children' => [
                ['id' => 'nstub01', 'type' => 'stub', 'props' => ['label' => 'Opening hours', 'description' => 'Send me the hours.']],
            ]]],
        ]];
        $site->initialise($document, 'First version');

        $master = (string) $container->getParameter('cadenza.token');
        $guest = McpGuardSubscriber::guestToken($master);
        self::assertNotSame($master, $guest);
        $init = (string) json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'test', 'version' => '1']]]);
        $knock = static function (string $server, ?string $token) use ($client, $init): int {
            $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'];
            if (null !== $token) {
                $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
            }
            $client->request('POST', '/_cadenza/mcp/'.$server, [], [], $headers, $init);

            return $client->getResponse()->getStatusCode();
        };
        self::assertSame(401, $knock('guest', null), 'no token, no door');
        self::assertSame(401, $knock('control', $guest), 'the guest token does not publish');
        self::assertSame(401, $knock('agent', $guest), 'the guest token does not open the studio\'s server');
        self::assertSame(200, $knock('guest', $guest));
        self::assertStringContainsString('cadenza-site-guest', (string) $client->getResponse()->getContent());

        /** @var GuestTools $tools */
        $tools = $container->get(GuestTools::class);
        self::assertStringContainsString('nhero01', $tools->outline());
        $result = $tools->propose([['op' => 'node.update', 'id' => 'nhero01', 'props' => ['title' => 'Good bread takes its time']]], 'A warmer opening line.', 'Claude <b>Code</b>');
        self::assertTrue($result['ok'], json_encode($result) ?: '');
        $versions = $container->get(SiteVersionRepository::class);
        $draft = $versions->draft((int) $result['draftId']);
        self::assertNotNull($draft);
        self::assertSame('Claude b Code b', $draft->getAuthor(), 'the name is plain text');
        self::assertCount(1, $versions->drafts(40, true));

        // A draft of the studio is not the guest's to touch.
        $own = $site->propose([['op' => 'node.update', 'id' => 'nhero01', 'props' => ['title' => 'Studio']]], 'By the studio')->draft;
        self::assertFalse($tools->propose([['op' => 'node.update', 'id' => 'nhero01', 'props' => ['title' => 'Mine now']]], 'x', 'Guest', $own->getId())['ok']);

        // The live site did not change, and a visitor sees neither the sketch nor the owner's placeholders.
        $client->request('GET', '/');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Slow bread', $html);
        self::assertStringNotContainsString('Good bread takes its time', $html);
        self::assertStringNotContainsString('Send me the hours', $html);
        self::assertStringContainsString('cz-visitor', $html);
        self::assertStringNotContainsString($guest, $html, 'tokens are for the owner\'s eyes');
    }
}
