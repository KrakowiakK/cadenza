<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Site\Document;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The owner's way in: the first account from the site's own computer, signing out and in, wrong passwords, and the
 * setup screen closing for good once there is an owner.
 */
final class OwnerLoginTest extends WebTestCase
{
    public function testFirstRunSignOutAndSignIn(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        $container->get(SiteManager::class)->initialise(Document::empty('Bakery'), 'Blank');

        // Nobody is the owner yet: from another machine the setup screen does not exist, without the setup token.
        $client->request('GET', '/_cadenza/setup', [], [], ['REMOTE_ADDR' => '203.0.113.7']);
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/_cadenza/setup?token='.$container->getParameter('cadenza.token'), [], [], ['REMOTE_ADDR' => '203.0.113.7']);
        self::assertResponseIsSuccessful();

        // From the site's own computer, the login page leads to the setup screen.
        $client->request('GET', '/_cadenza/login');
        self::assertResponseRedirects('/_cadenza/setup');
        $client->followRedirect();
        $client->submitForm('Utwórz konto i wejdź', ['email' => 'ania@example.com', 'password' => 'short', 'repeat' => 'short']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role=alert]', '10 znaków');
        $client->submitForm('Utwórz konto i wejdź', ['email' => 'ania@example.com', 'password' => 'kazimierz-2027', 'repeat' => 'kazimierz-2027']);
        self::assertResponseRedirects('/');

        $client->request('GET', '/_cadenza/dashboard');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#account', 'ania@example.com');

        $client->request('GET', '/_cadenza/logout');
        $client->request('GET', '/_cadenza/dashboard');
        self::assertResponseStatusCodeSame(404);

        // The setup screen is gone for good.
        $client->request('GET', '/_cadenza/setup');
        self::assertResponseRedirects('/_cadenza/login');

        $client->request('GET', '/_cadenza/login');
        $client->submitForm('Zaloguj', ['_username' => 'ania@example.com', '_password' => 'wrong-password']);
        $client->followRedirect();
        self::assertSelectorTextContains('[role=alert]', 'Nieprawidłowy');

        $client->submitForm('Zaloguj', ['_username' => 'ania@example.com', '_password' => 'kazimierz-2027']);
        self::assertResponseRedirects();
        $client->request('GET', '/');
        self::assertSelectorExists('#cz-panel');
    }
}
