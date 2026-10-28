<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Entity\Account;
use Cadenza\Runtime\Entity\Submission;
use Cadenza\Runtime\Site\Document;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A visitor answers a published form: invalid input is refused field by field, places are counted, the form closes
 * when full, and only the owner downloads the answers.
 */
final class FormSubmissionTest extends WebTestCase
{
    public function testVisitorsAnswerAnRsvp(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        $container->get('limiter.cadenza_form')->create('127.0.0.1')->reset();

        $site = $container->get(SiteManager::class);
        $site->initialise(Document::empty('Wedding'), 'Blank');
        $result = $site->propose([['op' => 'page.create', 'path' => '/', 'title' => 'RSVP', 'nodes' => [[
            'id' => 'rsvp',
            'type' => 'form',
            'props' => [
                'title' => 'Will you come?',
                'capacity' => 5,
                'peopleField' => 'Guests',
                'fields' => [
                    ['label' => 'Name', 'type' => 'text', 'required' => true],
                    ['label' => 'E-mail', 'type' => 'email'],
                    ['label' => 'Guests', 'type' => 'number', 'required' => true],
                    ['label' => 'Menu', 'type' => 'radio', 'options' => ['Meat', 'Vegetarian'], 'required' => true],
                ],
            ],
        ]]]], 'RSVP');
        self::assertTrue($result->isValid(), implode("\n", $result->errors));

        // A draft does not collect answers yet.
        $client->request('POST', '/_cadenza/form/rsvp', ['f' => ['Ann', '', '2', 'Meat']], [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(404);

        $site->publish((int) $result->draft->getId());
        $client->request('GET', '/?cadenza_guest=1');
        self::assertSelectorExists('form[data-cz-form]');
        self::assertSelectorTextContains('[data-cz-left]', '5 / 5');

        $client->request('POST', '/_cadenza/form/rsvp', ['f' => ['', 'not-an-email', '2', 'Fish']], [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['0' => 'required', '1' => 'email', '3' => 'option'], json_decode((string) $client->getResponse()->getContent(), true)['errors']);

        $client->request('POST', '/_cadenza/form/rsvp', ['f' => ['Ann', 'ann@example.com', '2', 'Vegetarian']], [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        self::assertSame(3, json_decode((string) $client->getResponse()->getContent(), true)['left']);

        // A bot fills in the hidden field: it looks accepted but nothing is stored.
        $client->request('POST', '/_cadenza/form/rsvp', ['f' => ['Bot', '', '1', 'Meat'], 'cz_website' => 'spam'], [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();

        $client->request('POST', '/_cadenza/form/rsvp', ['f' => ['Bob', '', '4', 'Meat']], [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('not_enough', json_decode((string) $client->getResponse()->getContent(), true)['error']);

        $client->request('POST', '/_cadenza/form/rsvp', ['f' => ['Bob', '', '3', 'Meat']], [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        self::assertSame(2, $em->getRepository(Submission::class)->count([]));

        $client->request('GET', '/?cadenza_guest=1');
        self::assertSelectorNotExists('form[data-cz-form]');

        $client->request('GET', '/_cadenza/form/rsvp/answers.xlsx');
        self::assertResponseStatusCodeSame(403);
        $owner = new Account('owner@example.com', [Account::OWNER]);
        $em->persist($owner);
        $em->flush();
        $client->loginUser($owner);
        $client->request('GET', '/_cadenza/form/rsvp/answers.xlsx');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('attachment', (string) $client->getResponse()->headers->get('Content-Disposition'));
    }
}
