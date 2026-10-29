<?php

declare(strict_types=1);

namespace App\Tests\Vault;

use App\Connection\Subscriptions;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class SubscriptionsTest extends TestCase
{
    public function testModelNamesOfASubscription(): void
    {
        self::assertSame(['claude-code', 'sonnet'], Subscriptions::parse('claude-code:sonnet'));
        self::assertSame(['codex', 'gpt-6.1-sol'], Subscriptions::parse('codex:gpt-6.1-sol'));
        self::assertNull(Subscriptions::parse('beam?max_tokens=12000'));
        self::assertNull(Subscriptions::parse(null));
    }

    public function testAConversationIsWrittenOutIntoOnePrompt(): void
    {
        // The CLIs get the last message of the owner only: the code and the error must travel in it.
        $flat = Subscriptions::flattened(new MessageBag(
            Message::forSystem('Write widgets.'),
            Message::ofUser('A counter.'),
            Message::ofAssistant('<button>0</button>'),
            Message::ofUser('It fails with: x is not defined'),
        ));

        $messages = $flat->withoutSystemMessage()->getMessages();
        self::assertCount(1, $messages);
        $prompt = $messages[0]->asText();
        self::assertStringContainsString('A counter.', $prompt);
        self::assertStringContainsString('<button>0</button>', $prompt);
        self::assertStringContainsString('x is not defined', $prompt);
        self::assertSame('Write widgets.', (string) $flat->getSystemMessage()?->getContent());
    }

    public function testTheCliKeepsNoToolsOfItsOwn(): void
    {
        self::assertSame(['tools' => [], 'strict_mcp_config' => true], Subscriptions::options('claude-code'));
        self::assertSame('read-only', Subscriptions::options('codex')['sandbox']);
    }
}
