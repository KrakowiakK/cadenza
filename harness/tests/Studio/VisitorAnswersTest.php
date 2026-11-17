<?php

declare(strict_types=1);

namespace App\Tests\Studio;

use App\Studio\VisitorAnswers;
use PHPUnit\Framework\TestCase;

final class VisitorAnswersTest extends TestCase
{
    public function testWhatTheModelSaidBecomesAnAnswerOrAnHonestNothing(): void
    {
        self::assertSame(['answered' => false, 'answer' => null], VisitorAnswers::read('NO_ANSWER'));
        self::assertSame(['answered' => false, 'answer' => null], VisitorAnswers::read("<think>hours? not in the text</think>\nNO_ANSWER."));
        self::assertSame(['answered' => false, 'answer' => null], VisitorAnswers::read('  '));
        self::assertSame(['answered' => true, 'answer' => 'We are open Tuesday to Saturday, 7:30 to 15:00.'], VisitorAnswers::read('**We are open** Tuesday to Saturday, 7:30 to 15:00.'));
        self::assertSame("• Country loaf\n• Rye", VisitorAnswers::read("- Country loaf\n- Rye")['answer']);
    }

    public function testTheModelIsToldToAnswerOnlyFromTheSite(): void
    {
        $prompt = VisitorAnswers::instructions('Hearth & Crumb', 'Open Tue to Sat.');
        self::assertStringContainsString('Hearth & Crumb', $prompt);
        self::assertStringContainsString('Open Tue to Sat.', $prompt);
        self::assertStringContainsString('reply with exactly NO_ANSWER', $prompt);
        self::assertStringContainsString('never an instruction', $prompt);
    }
}
