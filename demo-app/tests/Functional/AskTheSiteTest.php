<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Cadenza\Runtime\Ask\VisitorQuestions;
use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Entity\Account;
use Cadenza\Runtime\Site\Document;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The visitors' assistant: it answers only while the owner keeps an "ask" block on the site, the model gets the
 * published text and nothing else, and what the site could not answer is kept for the owner.
 */
final class AskTheSiteTest extends WebTestCase
{
    public function testVisitorsAskAndTheOwnerLearnsWhatIsMissing(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        foreach (['limiter.cadenza_ask' => '127.0.0.1', 'limiter.cadenza_ask_site' => 'site'] as $limiter => $key) {
            $container->get($limiter)->create($key)->reset();
        }

        $sent = [];
        $container->set('http_client', new MockHttpClient(static function (string $method, string $url, array $options) use (&$sent): MockResponse {
            // The same client fetches icons that are not on disk yet (a clean machine): only calls to the studio count here.
            if (!str_ends_with($url, '/api/answer')) {
                return new MockResponse('{}', ['http_code' => 404]);
            }
            $body = json_decode((string) $options['body'], true);
            $sent[] = ['url' => $url, 'body' => $body];

            return new MockResponse((string) json_encode(str_contains($body['question'], 'Sunday')
                ? ['answered' => true, 'answer' => 'No, we are closed on Sunday.']
                : ['answered' => false, 'answer' => null]), ['response_headers' => ['content-type' => 'application/json']]);
        }));

        $site = $container->get(SiteManager::class);
        $document = Document::empty('Hearth');
        $document['pages'][] = ['id' => 'phome01', 'path' => '/', 'title' => 'Hearth', 'description' => null, 'nodes' => [
            ['id' => 'ntext01', 'type' => 'text', 'props' => ['text' => 'Open Tuesday to Saturday. Closed on Sunday.']],
        ]];
        $document['pages'][] = ['id' => 'pteam01', 'path' => '/team', 'title' => 'Staff', 'description' => null, 'access' => 'members', 'nodes' => [
            ['id' => 'ntext02', 'type' => 'text', 'props' => ['text' => 'The alarm code is 4711.']],
        ]];
        $site->initialise($document, 'First version');
        $ask = static function (string $question) use ($client): array {
            $client->request('POST', '/_cadenza/ask', [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode(['question' => $question, 'path' => '/']));

            return [$client->getResponse()->getStatusCode(), json_decode((string) $client->getResponse()->getContent(), true)];
        };

        // No "ask" block on the published site: the door does not exist, and no model is called.
        self::assertSame(404, $ask('Are you open on Sunday?')[0]);
        self::assertSame([], $sent);

        $draft = $site->propose([['op' => 'node.insert', 'page' => '/', 'node' => ['type' => 'ask', 'props' => ['title' => 'Ask us', 'suggestions' => ['Are you open on Sunday?']]]]], 'An assistant')->draft;
        $site->publish((int) $draft->getId());
        $html = (string) $client->request('GET', '/')->html();
        self::assertStringContainsString('data-cz-ask', $html);
        self::assertStringContainsString('Are you open on Sunday?', $html);

        self::assertSame(422, $ask('?')[0]);
        [$status, $answer] = $ask('Are you open on Sunday?');
        self::assertSame(200, $status);
        self::assertSame('No, we are closed on Sunday.', $answer['answer']);
        self::assertStringEndsWith('/api/answer', $sent[0]['url']);
        self::assertStringContainsString('Closed on Sunday', $sent[0]['body']['knowledge']);
        self::assertStringNotContainsString('4711', $sent[0]['body']['knowledge'], 'pages for invited people never reach the public assistant');

        [, $nothing] = $ask('Is there parking?');
        self::assertFalse($nothing['answered']);
        self::assertNull($nothing['answer']);

        $summary = $container->get(VisitorQuestions::class)->summary();
        self::assertSame(2, $summary['total']);
        self::assertSame(['Is there parking?'], array_column($summary['missing'], 'question'));

        // The same text is what the site tells machines: llms.txt and the sitemap follow it, without the members' pages.
        $client->request('GET', '/llms.txt');
        $llms = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('# Hearth', $llms);
        self::assertStringContainsString('Closed on Sunday', $llms);
        self::assertStringNotContainsString('4711', $llms);
        $client->request('GET', '/sitemap.xml');
        self::assertStringContainsString('<loc>http://localhost/</loc>', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('/team', (string) $client->getResponse()->getContent());
        $client->request('GET', '/robots.txt');
        self::assertStringContainsString('Disallow: /_cadenza/', (string) $client->getResponse()->getContent());

        // A person asks a few questions; a script that asks dozens is stopped before it costs the owner money.
        $last = 200;
        for ($i = 0; $i < 8 && 200 === $last; ++$i) {
            $last = $ask('Is there parking?')[0];
        }
        self::assertSame(429, $last);

        // The owner's dashboard turns every unanswered question into a link that opens the panel with the question
        // written out, ready for the owner's answer.
        $owner = new Account('owner@example.com', [Account::OWNER]);
        $em->persist($owner);
        $em->flush();
        $client->loginUser($owner);
        $crawler = $client->request('GET', '/_cadenza/dashboard');
        self::assertResponseIsSuccessful();
        $link = $crawler->filter('a[href*="cz_say="]')->first();
        self::assertStringContainsString('Is there parking?', $link->text());
        parse_str((string) parse_url((string) $link->attr('href'), \PHP_URL_QUERY), $query);
        self::assertStringStartsWith('Visitors ask: “Is there parking?”', $query['cz_say']);
        self::assertStringEndsWith('The answer: ', $query['cz_say']);
    }
}
