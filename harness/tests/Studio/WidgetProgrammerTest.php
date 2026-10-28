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
}
