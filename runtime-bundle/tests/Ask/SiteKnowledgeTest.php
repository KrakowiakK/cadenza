<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Tests\Ask;

use Cadenza\Runtime\Ask\SiteKnowledge;
use PHPUnit\Framework\TestCase;

final class SiteKnowledgeTest extends TestCase
{
    public function testThePublishedSiteAsPlainTextForTheVisitorsAssistant(): void
    {
        $text = SiteKnowledge::of([
            'name' => 'Hearth & Crumb',
            'tagline' => 'Sourdough bakery in Bristol',
            'footer' => ['text' => '12 Mill Lane, Bristol'],
            'pages' => [
                ['id' => 'p1', 'path' => '/', 'title' => 'Home', 'nodes' => [
                    ['id' => 'n1', 'type' => 'hero', 'props' => ['title' => 'Slow sourdough', 'variant' => 'split', 'image' => '/media/loaf.jpg', 'buttonHref' => '/visit'], 'slots' => ['actions' => [
                        ['id' => 'n2', 'type' => 'button', 'props' => ['label' => 'See what we bake', 'href' => '#menu']],
                    ]]],
                    ['id' => 'n3', 'type' => 'visit', 'props' => ['title' => 'Find us', 'phone' => '0117 496 0000', 'hours' => [['days' => 'Tue to Sat', 'time' => '7:30 to 15:00']]]],
                    ['id' => 'n4', 'type' => 'stub', 'props' => ['label' => 'Map', 'description' => 'Send me the address.']],
                    ['id' => 'n5', 'type' => 'ask', 'props' => ['title' => 'Ask us', 'suggestions' => ['Are you open on Sunday?']]],
                ]],
                ['id' => 'p2', 'path' => '/team', 'title' => 'For staff', 'access' => 'members', 'nodes' => [
                    ['id' => 'n6', 'type' => 'text', 'props' => ['text' => 'The alarm code is 4711.']],
                ]],
            ],
        ], ['Breads' => [['Name' => 'Country loaf', 'Price' => '£4.80']]]);

        self::assertStringContainsString('# Hearth & Crumb — Sourdough bakery in Bristol', $text);
        self::assertStringContainsString('Slow sourdough', $text);
        self::assertStringContainsString('See what we bake', $text);
        self::assertStringContainsString('phone: 0117 496 0000', $text, 'facts keep their name');
        self::assertStringContainsString('time: 7:30 to 15:00', $text);
        self::assertStringContainsString('Name: Country loaf; Price: £4.80', $text);
        self::assertStringContainsString('12 Mill Lane', $text);
        self::assertStringNotContainsString('split', $text, 'how a block looks is not knowledge');
        self::assertStringNotContainsString('/media/', $text);
        self::assertStringNotContainsString('Send me the address', $text, 'notes to the owner stay with the owner');
        self::assertStringNotContainsString('open on Sunday', $text, 'the assistant\'s own examples are not facts');
        self::assertStringNotContainsString('4711', $text, 'pages for invited people are not for the public assistant');
    }
}
