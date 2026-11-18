<?php

declare(strict_types=1);

namespace App\Tests\Vault;

use App\Vault\VaultEnvVarProcessor;
use PHPUnit\Framework\TestCase;

final class ModelNameTest extends TestCase
{
    public function testAPlainModelNameGetsRoomForAWholePage(): void
    {
        self::assertSame('deepseek-flash?max_tokens=12000', VaultEnvVarProcessor::model('deepseek-flash'));
        self::assertSame('beam?reasoning_effort=low&max_tokens=12000', VaultEnvVarProcessor::model('beam?reasoning_effort=low'));
    }

    public function testANameThatSetsItsOwnLimitIsLeftAlone(): void
    {
        self::assertSame('deepseek-flash?max_tokens=500', VaultEnvVarProcessor::model('deepseek-flash?max_tokens=500'));
        self::assertSame('gpt-x?max_completion_tokens=900', VaultEnvVarProcessor::model('gpt-x?max_completion_tokens=900'));
        self::assertSame('', VaultEnvVarProcessor::model(' '));
    }
}
