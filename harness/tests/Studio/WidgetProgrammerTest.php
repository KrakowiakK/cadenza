<?php

declare(strict_types=1);

namespace App\Tests\Studio;

use App\Studio\WidgetProgrammer;
use PHPUnit\Framework\TestCase;

final class WidgetProgrammerTest extends TestCase
{
    public function testTheCodeIsTakenOutOfTheAnswer(): void
    {
        self::assertSame('<canvas></canvas>', WidgetProgrammer::extract("<think>plan</think>Here it is:\n```html\n<canvas></canvas>\n```\nEnjoy!"));
        self::assertSame('<p>cut off', WidgetProgrammer::extract("```html\n<p>cut off"), 'an unfinished block still counts');
        self::assertSame('<div>x</div>', WidgetProgrammer::extract('<div>x</div>'));
    }

    public function testSyntaxErrorsAreFoundBeforeTheOwnerSeesThem(): void
    {
        $node = is_executable('/opt/homebrew/bin/node') || is_executable('/usr/local/bin/node') || null !== (new \Symfony\Component\Process\ExecutableFinder())->find('node');
        self::assertSame([], WidgetProgrammer::syntaxErrors("<script type=\"module\">import * as THREE from 'three';\nconst a = 1;</script>"));
        self::assertNotSame([], WidgetProgrammer::syntaxErrors('<script>const a = 1;'), 'a script cut off');
        if (!$node) {
            self::markTestIncomplete('Node is not installed');
        }
        $errors = WidgetProgrammer::syntaxErrors("<script type=\"module\">\nconst a = 1;\nconst b = (a;\n</script>");
        self::assertCount(1, $errors);
        self::assertStringContainsString('line 3', $errors[0]);
        self::assertStringContainsString('SyntaxError', $errors[0]);
    }

    public function testCodeWithASyntaxErrorGoesBackToTheProgrammer(): void
    {
        if (null === (new \Symfony\Component\Process\ExecutableFinder())->find('node', null, ['/opt/homebrew/bin', '/usr/local/bin'])) {
            self::markTestSkipped('Node is not installed');
        }
        $answers = ["```html\n<script type=\"module\">const a = (1;</script>\n```", "```html\n<script type=\"module\">const a = 1;</script>\n```"];
        $calls = 0;
        $platform = \Symfony\AI\Platform\Test\MockPlatformFactory::createPlatform(static function () use (&$calls, $answers): \Symfony\AI\Platform\Result\StreamResult {
            $text = $answers[$calls++];

            return new \Symfony\AI\Platform\Result\StreamResult((static function () use ($text): \Generator {
                yield new \Symfony\AI\Platform\Result\Stream\Delta\TextDelta($text);
            })());
        });
        $steps = [];
        $code = (new WidgetProgrammer($platform, 'any-model'))->write('a counter', 'pl', ['three' => '0.186.1'], 'wide', progress: static function (string $s) use (&$steps): void { $steps[] = $s; });

        self::assertSame('<script type="module">const a = 1;</script>', $code);
        self::assertSame(2, $calls);
        self::assertContains('fixing', $steps);
    }
}
