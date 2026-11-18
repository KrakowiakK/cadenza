<?php

declare(strict_types=1);

namespace App\Vault;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;

/**
 * %env(vault:CADENZA_LLM_API_KEY)%: the setting from the environment the studio was started with, else from the vault
 * (what the owner set in the panel), else from the .env files — the vault wins over .env, so connecting a model in the
 * panel needs no file edited, while a key exported by a start script still wins over both.
 */
final class VaultEnvVarProcessor implements EnvVarProcessorInterface
{
    public function __construct(private readonly Vault $vault)
    {
    }

    public function getEnv(string $prefix, string $name, \Closure $getEnv): mixed
    {
        $value = $this->read($name, $getEnv);

        if (str_ends_with($name, '_MODEL') && \is_string($value)) {
            return self::model($value);
        }

        return str_ends_with($name, '_BASE_URL') && \is_string($value) ? self::baseUrl($value) : $value;
    }

    /**
     * The address of a model service the way the platform wants it: most people write "https://host/api/v1" (or paste the
     * whole ".../v1/chat/completions"), the platform adds "/v1/..." itself.
     */
    public static function baseUrl(string $url): string
    {
        return preg_replace('~(/v1)?(/chat/completions)?/*$~', '', trim($url)) ?? $url;
    }

    /**
     * A model name the way the agent needs it: a whole page travels in one tool call, and services that default to a
     * few thousand output tokens cut it mid-JSON ("malformed arguments"). A plain name gets a roomy limit; a name that
     * sets its own is left alone.
     */
    public static function model(string $model): string
    {
        $model = trim($model);
        if ('' === $model || 1 === preg_match('/[?&]max_(completion_)?tokens=/', $model)) {
            return $model;
        }

        return $model.(str_contains($model, '?') ? '&' : '?').'max_tokens=12000';
    }

    private function read(string $name, \Closure $getEnv): mixed
    {
        try {
            $value = $getEnv($name);
        } catch (EnvNotFoundException) {
            $value = null;
        }
        if (null !== $value && '' !== $value && !self::fromDotenv($name)) {
            return $value;
        }
        $stored = $this->vault->get($name);
        if (null !== $stored) {
            return $stored;
        }
        if (null === $value) {
            throw new EnvNotFoundException(\sprintf('Environment variable not found: "%s" (nor in the vault).', $name));
        }

        return $value;
    }

    public static function getProvidedTypes(): array
    {
        return ['vault' => 'string'];
    }

    /**
     * Whether the variable was set by a .env file rather than by the environment itself.
     */
    public static function fromDotenv(string $name): bool
    {
        $loaded = $_SERVER['SYMFONY_DOTENV_VARS'] ?? $_ENV['SYMFONY_DOTENV_VARS'] ?? '';

        return \in_array($name, explode(',', (string) $loaded), true);
    }
}
