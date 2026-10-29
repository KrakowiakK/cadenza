<?php

declare(strict_types=1);

namespace App\Tests\Vault;

use App\Connection\Connections;
use App\Connection\Providers;
use App\Vault\Vault;
use App\Vault\VaultEnvVarProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;

final class VaultTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/cz-vault-test-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->dir);
        unset($_SERVER['CZ_TEST_SETTING'], $_SERVER['CZ_TEST_SOURCE'], $_SERVER['SYMFONY_DOTENV_VARS']);
    }

    public function testValuesAreEncryptedAndReadBackByAnotherInstance(): void
    {
        (new Vault($this->dir))->setMany(['CADENZA_LLM_API_KEY' => 'sk-very-secret', 'CADENZA_LLM_MODEL' => 'beam']);

        $vault = new Vault($this->dir);
        self::assertSame('sk-very-secret', $vault->get('CADENZA_LLM_API_KEY'));
        self::assertSame('vault', $vault->source('CADENZA_LLM_API_KEY'));
        self::assertStringNotContainsString('sk-very-secret', (string) file_get_contents($this->dir.'/vault.json'));
        self::assertSame('0600', substr(\sprintf('%o', fileperms($this->dir.'/vault.key')), -4));

        $vault->set('CADENZA_LLM_MODEL', null);
        self::assertNull((new Vault($this->dir))->get('CADENZA_LLM_MODEL'));
    }

    public function testASettingCanPointToAnEnvironmentVariableWithoutCopyingIt(): void
    {
        $_SERVER['CZ_TEST_SOURCE'] = 'from-the-environment';
        $vault = new Vault($this->dir);
        $vault->point('PEXELS_API_KEY', 'CZ_TEST_SOURCE');

        self::assertSame('from-the-environment', $vault->get('PEXELS_API_KEY'));
        self::assertSame('env:CZ_TEST_SOURCE', $vault->source('PEXELS_API_KEY'));
        self::assertStringNotContainsString('from-the-environment', (string) file_get_contents($this->dir.'/vault.json'));
    }

    public function testTheEnvironmentWinsOverTheVaultWhichWinsOverDotenv(): void
    {
        $vault = new Vault($this->dir);
        $vault->set('CZ_TEST_SETTING', 'from-vault');
        $processor = new VaultEnvVarProcessor($vault);

        // Exported by a start script: it wins.
        self::assertSame('from-script', $processor->getEnv('vault', 'CZ_TEST_SETTING', static fn (): string => 'from-script'));
        // Written by a .env file: the vault wins.
        $_SERVER['SYMFONY_DOTENV_VARS'] = 'APP_ENV,CZ_TEST_SETTING';
        self::assertSame('from-vault', $processor->getEnv('vault', 'CZ_TEST_SETTING', static fn (): string => 'from-dotenv'));
        // Nowhere but in the vault.
        self::assertSame('from-vault', $processor->getEnv('vault', 'CZ_TEST_SETTING', static fn () => throw new EnvNotFoundException('x')));
    }

    public function testAMissingSettingIsNotFoundSoDefaultsApply(): void
    {
        $this->expectException(EnvNotFoundException::class);
        (new VaultEnvVarProcessor(new Vault($this->dir)))->getEnv('vault', 'CZ_NOWHERE', static fn () => throw new EnvNotFoundException('x'));
    }

    public function testProvidersAreRecognisedByTheirAddress(): void
    {
        self::assertSame('ollama-cloud', Providers::byBaseUrl('https://ollama.com'));
        self::assertSame('openrouter', Providers::byBaseUrl('https://openrouter.ai/api'));
        self::assertSame('lmstudio', Providers::byBaseUrl('http://127.0.0.1:1242'));
        self::assertSame('ollama', Providers::byBaseUrl('http://127.0.0.1:11434'));
        self::assertSame('custom', Providers::byBaseUrl('https://llm.example.org'));
        self::assertSame(['gpt-5-mini', 'a-model', 'b-model'], Connections::ordered(['b-model', 'gpt-5-mini', 'a-model'], Providers::MODELS['openai']['prefer']));
    }
}
