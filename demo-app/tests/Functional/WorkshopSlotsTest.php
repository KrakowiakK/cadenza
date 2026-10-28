<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Site\Document;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Sign-ups where every date has its own places: a full date closes, the others stay open.
 */
final class WorkshopSlotsTest extends WebTestCase
{
    public function testEveryDateHasItsOwnPlaces(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        $container->get('limiter.cadenza_form')->create('127.0.0.1')->reset();
        $site = $container->get(SiteManager::class);
        $site->initialise(Document::empty('Yoga'), 'Blank');
        $result = $site->propose([
            ['op' => 'site.update', 'language' => 'pl'],
            ['op' => 'page.create', 'path' => '/', 'title' => 'Warsztaty', 'nodes' => [[
                'id' => 'zapisy',
                'type' => 'form',
                'props' => [
                    'peopleField' => 'Ile osób',
                    'fields' => [
                        ['label' => 'Imię', 'type' => 'text', 'required' => true],
                        ['label' => 'Termin', 'type' => 'radio', 'required' => true, 'options' => ['Sobota 10:00', 'Niedziela 10:00'], 'limit' => 3],
                        ['label' => 'Ile osób', 'type' => 'number'],
                    ],
                ],
            ]]],
        ], 'Workshops');
        self::assertTrue($result->isValid(), implode("\n", $result->errors));
        $site->publish((int) $result->draft->getId());

        $send = static function (array $values) use ($client): array {
            $client->request('POST', '/_cadenza/form/zapisy', ['f' => $values], [], ['HTTP_ACCEPT' => 'application/json']);

            return [$client->getResponse()->getStatusCode(), json_decode((string) $client->getResponse()->getContent(), true)];
        };

        self::assertSame(200, $send(['Ola', 'Sobota 10:00', '2'])[0]);
        [$status, $body] = $send(['Jan', 'Sobota 10:00', '2']);
        self::assertSame(409, $status);
        self::assertSame(['1' => 'option_full'], $body['errors']);
        self::assertSame(200, $send(['Jan', 'Sobota 10:00', '1'])[0]);
        self::assertSame(200, $send(['Ewa', 'Niedziela 10:00', '1'])[0]);

        $crawler = $client->request('GET', '/');
        self::assertCount(1, $crawler->filter('input[value="Sobota 10:00"][disabled]'));
        self::assertCount(0, $crawler->filter('input[value="Niedziela 10:00"][disabled]'));
        self::assertStringContainsString('2 wolne', $crawler->filter('input[value="Niedziela 10:00"]')->ancestors()->first()->text());
    }
}
