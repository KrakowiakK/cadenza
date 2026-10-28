<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Cadenza\Runtime\Draft\SiteManager;
use Cadenza\Runtime\Site\Document;
use Cadenza\Runtime\Widget\Widgets;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Widgets the agent writes with real code — games, 3D scenes — run sandboxed: their own document, an opaque origin,
 * no network but the site's libraries and photos, and the sandbox comes with the response itself.
 */
final class WidgetsTest extends WebTestCase
{
    public function testAWidgetRunsInASandbox(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        $widgets = $container->get(Widgets::class);

        self::assertNotSame([], $widgets->save('spy', 'Spy', 'x', '<script src="https://evil.example.com/x.js"></script>')['errors']);
        self::assertNotSame([], $widgets->save('spy', 'Spy', 'x', '<script type="module">import x from "left-pad";</script>')['errors'], 'only the site\'s libraries');

        // Libraries from a CDN become the site's own copies; a whole document is reduced to its body and styles.
        $saved = $widgets->save('cube', 'Spinning cube', 'A 3D cube', <<<'HTML'
            <!doctype html><html><head><title>x</title><style>canvas{width:100%}</style></head><body>
            <canvas id="c"></canvas>
            <img src="/media/photo.jpg" alt="">
            <script type="module">
            import * as THREE from 'https://unpkg.com/three@0.160.0/build/three.module.js';
            import { OrbitControls } from 'https://cdn.jsdelivr.net/npm/three@0.160/examples/jsm/controls/OrbitControls.js';
            localStorage.setItem('best', '42');
            </script></body></html>
            HTML);
        self::assertSame([], $saved['errors']);
        $code = $saved['widget']->getCode();
        self::assertStringContainsString("from 'three'", $code);
        self::assertStringContainsString("from 'three/addons/controls/OrbitControls.js'", $code);
        self::assertStringNotContainsString('<title>', $code);
        self::assertStringContainsString('<style>canvas{width:100%}</style>', $code);

        $client->request('GET', '/_cadenza/widget/cube');
        self::assertResponseIsSuccessful();
        $csp = (string) $client->getResponse()->headers->get('Content-Security-Policy');
        self::assertStringStartsWith('sandbox allow-scripts', $csp, 'sandboxed even when opened directly');
        self::assertStringContainsString("default-src 'none'", $csp);
        self::assertStringNotContainsString('allow-same-origin', $csp);
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('"three":"http://localhost/_cadenza/widget-lib/three?v=', $html);
        self::assertStringContainsString('src="http://localhost/_cadenza/widget-media/photo.jpg"', $html, 'photos come with CORS for textures');

        $client->request('GET', '/_cadenza/widget-lib/three');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', '*');
        $client->request('GET', '/_cadenza/widget-lib/chart.js/auto');
        self::assertStringContainsString('registerables', (string) $client->getResponse()->getContent());
        $client->request('GET', '/_cadenza/widget-lib/@hotwired/turbo');
        self::assertResponseStatusCodeSame(404, 'only libraries meant for widgets');
        $client->request('GET', '/_cadenza/widget-media/../../config/secrets.php');
        self::assertResponseStatusCodeSame(404);

        $site = $container->get(SiteManager::class);
        $site->initialise(Document::empty('Arcade'), 'Blank');
        $result = $site->propose([['op' => 'page.create', 'path' => '/', 'title' => 'Arcade', 'nodes' => [
            ['type' => 'hero', 'props' => ['title' => 'Play', 'variant' => 'typographic', 'widget' => 'cube']],
            ['type' => 'widget', 'props' => ['widget' => 'cube', 'title' => 'The cube', 'intro' => '**Drag** it, *gently* <script>alert(1)</script>', 'aspect' => 'square', 'caption' => 'Drag to turn it']],
        ]]], 'Arcade');
        self::assertTrue($result->isValid(), implode("\n", $result->errors));
        $site->publish((int) $result->draft->getId());

        $crawler = $client->request('GET', '/');
        self::assertCount(2, $crawler->filter('iframe[data-cz-widget="cube"]'));
        $frame = $crawler->filter('[data-node-type="widget"] iframe');
        self::assertSame('allow-scripts allow-pointer-lock', $frame->attr('sandbox'));
        self::assertSame('/_cadenza/widget/cube?v=1', $frame->attr('data-src'));
        self::assertSame('true', $crawler->filter('[data-node-type="hero"] iframe')->attr('aria-hidden'), 'a background is decoration');
        self::assertSelectorTextContains('[data-node-type="widget"] figcaption', 'Drag to turn it');
        self::assertSelectorTextContains('[data-node-type="widget"] .cz-lead strong', 'Drag', 'models write **bold** in short texts');
        self::assertSelectorTextContains('[data-node-type="widget"] .cz-lead em', 'gently');
        self::assertSelectorNotExists('[data-node-type="widget"] .cz-lead script');

        // Errors from a visitor's browser are not kept; only what the owner sees.
        $client->request('POST', '/_cadenza/widget/cube/error', [], [], ['CONTENT_TYPE' => 'application/json'], '{"version":1,"error":"x is not defined"}');
        self::assertResponseStatusCodeSame(404);
        self::assertTrue($widgets->reportError('cube', 1, 'THREE is not defined (line 3)'));
        self::assertSame('THREE is not defined (line 3)', $widgets->get('cube')->getLastError());
        self::assertSame([], $widgets->save('cube', 'Spinning cube', 'A 3D cube', '<canvas></canvas>')['errors']);
        self::assertNull($widgets->get('cube')->getLastError(), 'a new version starts clean');
    }
}
