<?php

declare(strict_types=1);

namespace App\Tests\Vault;

use App\Controller\ChatController;
use App\Vault\VaultEnvVarProcessor;
use PHPUnit\Framework\TestCase;

final class BaseUrlTest extends TestCase
{
    public function testAnAddressWithV1WorksLikeOneWithout(): void
    {
        self::assertSame('https://openrouter.ai/api', VaultEnvVarProcessor::baseUrl('https://openrouter.ai/api/v1'));
        self::assertSame('https://openrouter.ai/api', VaultEnvVarProcessor::baseUrl('https://openrouter.ai/api/v1/'));
        self::assertSame('http://127.0.0.1:1242', VaultEnvVarProcessor::baseUrl(' http://127.0.0.1:1242/v1/chat/completions'));
        self::assertSame('https://api.deepseek.com', VaultEnvVarProcessor::baseUrl('https://api.deepseek.com'));
        self::assertSame('https://openrouter.ai/api', VaultEnvVarProcessor::baseUrl('https://openrouter.ai/api'));
    }

    public function testAFailureOfTheModelServiceSaysWhatToDo(): void
    {
        self::assertStringContainsString('"x:free" at https://h', ChatController::explain('Error "404"-- (-): "Not Found".', 'x:free', 'https://h'));
        self::assertStringContainsString('refused the key', ChatController::explain('HTTP 401 Unauthorized', 'm', 'u'));
        self::assertStringContainsString('too many requests', ChatController::explain('Rate limit exceeded (429)', 'm', 'u'));
        self::assertSame('The site rejected the change.', ChatController::explain('The site rejected the change.', 'm', 'u'));
    }
}
