<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Cadenza\Runtime\Block\CustomBlocks;
use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Site\Document;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Blocks the agent writes itself: they work like built-in ones, and nothing they contain can reach the site's
 * secrets, run a script or load something from elsewhere.
 */
final class CustomBlocksTest extends WebTestCase
{
    private const RECIPE = <<<'TWIG'
        <article class="cz-card p-6">
            <h3 class="text-2xl font-semibold">{{ icon('chef-hat', 'size-6 text-primary') }} {{ props.title }}</h3>
            <p class="opacity-70">{{ props.minutes }} min · {{ props.servings }}</p>
            <ul>{% for i in props.ingredients %}<li>{{ i.amount }} {{ i.name }}</li>{% endfor %}</ul>
            <div>{{ props.method|markdown }}</div>
        </article>
        TWIG;

    public function testAnAgentWrittenBlockRendersSafely(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        $blocks = $container->get(CustomBlocks::class);
        $props = ['title' => ['type' => 'string', 'required' => true], 'minutes' => 'int', 'servings' => 'string', 'method' => 'markdown', 'source' => 'string',
            'ingredients' => ['type' => 'list', 'items' => ['amount' => 'string', 'name' => 'string']]];

        // What the sandbox and the checks refuse, each with a reason the agent can act on.
        $refused = [
            'raw output' => '{{ props.title|raw }}',
            'including files' => "{% include 'base.html.twig' %}",
            'a script' => '<script>alert(1)</script>',
            'an event handler' => '<img src="/media/a.jpg" onerror="alert(1)">',
            'a style sheet' => '<style>body{display:none}</style>',
            'a form' => '<form action="https://example.com"><input name="card"></form>',
            'a javascript: link' => '<a href="javascript:alert(1)">x</a>',
        ];
        foreach ($refused as $what => $template) {
            self::assertNotSame([], $blocks->save('evil-block', 'Evil', 'x', $props, $template)['errors'], $what.' must be refused');
        }
        self::assertNotSame([], $blocks->save('hero', 'Hero', 'x', $props, '<p>x</p>')['errors'], 'built-in names are taken');

        $saved = $blocks->save('recipe-card', 'Recipe card', 'A recipe with ingredients and method.', $props, self::RECIPE
            // Nothing of the site leaks: no globals exist in the blocks' own Twig environment.
            .'<p class="leak">{{ cadenza_token }}{{ app.request.uri|default("") }}</p>'
            // A picture from elsewhere, and a dangerous link that comes from the data, are stripped from the output.
            .'<img src="https://tracker.example.com/pixel.gif" alt="t"><a class="src" href="{{ props.source }}">source</a>');
        self::assertSame([], $saved['errors']);

        $site = $container->get(SiteManager::class);
        $site->initialise(Document::empty('Kitchen'), 'Blank');
        $result = $site->propose([['op' => 'page.create', 'path' => '/', 'title' => 'Recipes', 'nodes' => [['type' => 'recipe-card', 'props' => [
            'title' => 'Pierogi <script>alert(1)</script>', 'minutes' => '45', 'servings' => '4 osoby',
            'ingredients' => [['amount' => '500 g', 'name' => 'mąki'], ['amount' => '1', 'name' => 'jajko']],
            'method' => "**Zagnieć** ciasto.\n\n<img src=x onerror=alert(1)>",
            'source' => 'javascript:alert(document.cookie)',
        ]]]]], 'Recipe');
        self::assertTrue($result->isValid(), implode("\n", $result->errors));
        $site->publish((int) $result->draft->getId());

        $crawler = $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.cz-node-error');
        self::assertSelectorTextContains('[data-node-type="recipe-card"] h3', 'Pierogi <script>alert(1)</script>');
        self::assertCount(2, $crawler->filter('[data-node-type="recipe-card"] li'));
        self::assertSelectorExists('[data-node-type="recipe-card"] h3 svg', 'icons are drawn after sanitising');
        self::assertSelectorExists('[data-node-type="recipe-card"] strong');
        $html = $crawler->filter('[data-node-type="recipe-card"]')->html();
        self::assertStringNotContainsString('<script', $html);
        self::assertCount(0, $crawler->filter('[data-node-type="recipe-card"] [onerror]'), 'markup in the data stays text');
        self::assertNull($crawler->filter('[data-node-type="recipe-card"] a.src')->attr('href'));
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('tracker.example.com', $html);
        self::assertSame('', trim($crawler->filter('.leak')->text()), 'no token, no request: the block sees only its props');
    }
}
