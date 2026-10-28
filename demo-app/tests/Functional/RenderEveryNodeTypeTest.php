<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Cadenza\Runtime\Catalog\Catalog;
use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Entity\Account;
use Cadenza\Runtime\Site\Document;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Every node type of the catalog, filled with sample props, renders on a real page (sketch and published) without
 * an error box — the safety net for templates.
 */
final class RenderEveryNodeTypeTest extends WebTestCase
{
    public function testEveryNodeTypeRenders(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());

        $site = $container->get(SiteManager::class);
        $site->initialise(Document::empty('Test'), 'Blank');

        $nodes = [];
        foreach ($container->get(Catalog::class)->all() as $type) {
            $props = [];
            foreach ($type->props as $name => $schema) {
                if ($schema['required'] ?? false) {
                    $props[$name] = self::sample($schema);
                }
            }
            $nodes[] = ['type' => $type->name, 'props' => $props];
        }
        $result = $site->propose([['op' => 'theme.update', 'direction' => 'warm'], ['op' => 'page.create', 'path' => '/', 'title' => 'Everything', 'nodes' => $nodes]], 'All node types');
        self::assertTrue($result->isValid(), implode("\n", $result->errors));

        $owner = new Account('owner@example.com', [Account::OWNER]);
        $em->persist($owner);
        $em->flush();
        $client->loginUser($owner);
        $client->request('GET', '/?cz_draft='.$result->draft->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.cz-node-error');
        self::assertSelectorExists('#cz-sketch-banner');

        $site->publish((int) $result->draft->getId());
        $client->request('GET', '/?cadenza_guest=1');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.cz-node-error');
        self::assertSelectorNotExists('#cz-panel');
        self::assertSame(\count($nodes), $client->getCrawler()->filter('main > .cz-node')->count());
    }

    /**
     * @param array<string, mixed> $schema
     */
    private static function sample(array $schema): mixed
    {
        return match ($schema['type']) {
            'enum' => $schema['values'][0],
            'int' => 0,
            'bool' => true,
            'url' => '/',
            'image' => '/media/sample.jpg',
            'file' => '/files/sample.pdf',
            'date' => '2030-06-14',
            'time' => '15:00',
            'list' => isset($schema['items'])
                ? [array_map(static fn (array $s): mixed => self::sample($s), array_filter($schema['items'], static fn (array $s): bool => $s['required'] ?? false))]
                : [self::sample(['type' => $schema['of'] ?? 'string'])],
            default => 'Sample',
        };
    }
}
