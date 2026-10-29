<?php

declare(strict_types=1);

namespace App\Connection;

use Symfony\AI\Platform\Bridge\ClaudeCode\Factory as ClaudeCodeFactory;
use Symfony\AI\Platform\Bridge\Codex\Codex;
use Symfony\AI\Platform\Bridge\Codex\Factory as CodexFactory;
use Symfony\AI\Platform\Bridge\Codex\ModelCatalog as CodexModelCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The owner's own plans for coding agents — Claude Code (Claude plan) and Codex (ChatGPT plan) — used through their
 * CLIs, signed in on this computer, with Symfony AI's bridges: a model name like "claude-code:sonnet" or
 * "codex:gpt-6.1-sol". They write text only (no tool calls), so they write widget code, not the chat. The CLIs run with
 * every tool of their own switched off (Claude Code: no tools, no MCP servers; Codex: a read-only sandbox) in an
 * empty directory, so they can answer and nothing else.
 */
final class Subscriptions
{
    /** id => label */
    public const CLIS = ['claude-code' => 'Claude Code', 'codex' => 'Codex'];

    /** @var array<string, PlatformInterface> */
    private array $platforms = [];

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/cli')]
        private readonly string $directory,
    ) {
    }

    /**
     * @return array{0: string, 1: string}|null [CLI id, model] for a model name of a subscription
     */
    public static function parse(?string $model): ?array
    {
        if (null === $model || !preg_match('~^(claude-code|codex):([\w.\-]+)$~', $model, $m)) {
            return null;
        }

        return [$m[1], $m[2]];
    }

    public function platform(string $id): PlatformInterface
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0700, true);
        }
        $binary = Discovery::binary('claude-code' === $id ? 'claude' : 'codex');
        // A web server's environment is short: the CLI finds its sign-in by HOME (and Claude Code in the keychain by USER).
        $environment = ['HOME' => Discovery::home(), 'USER' => Discovery::user(), 'PATH' => (null !== $binary ? \dirname($binary).':' : '').'/usr/bin:/bin'];

        return $this->platforms[$id] ??= match ($id) {
            'claude-code' => ClaudeCodeFactory::createPlatform($binary, $this->directory, 900, $environment),
            // The models a ChatGPT plan offers change faster than the bridge's list: the ones Codex itself knows.
            'codex' => CodexFactory::createPlatform($binary, $this->directory, 900, $environment, new CodexModelCatalog(array_fill_keys(self::models('codex'), ['class' => Codex::class, 'capabilities' => [Capability::INPUT_MESSAGES, Capability::INPUT_TEXT, Capability::OUTPUT_TEXT, Capability::OUTPUT_STREAMING]]))),
            default => throw new \InvalidArgumentException(\sprintf('Unknown subscription "%s".', $id)),
        };
    }

    /**
     * The models of a subscription, its default first: Claude Code's aliases; for Codex the model of its settings and
     * the ones it lists (from its own cache — nothing else of its files is read).
     *
     * @return list<string>
     */
    public static function models(string $id): array
    {
        if ('claude-code' === $id) {
            return ['sonnet', 'opus', 'haiku'];
        }
        $home = Discovery::home().'/.codex';
        $models = [];
        if (is_file($home.'/config.toml') && preg_match('~^\s*model\s*=\s*"([^"]+)"~m', (string) file_get_contents($home.'/config.toml'), $m)) {
            $models[] = $m[1];
        }
        $cache = is_file($home.'/models_cache.json') ? json_decode((string) file_get_contents($home.'/models_cache.json'), true) : null;
        foreach ((\is_array($cache) ? ($cache['models'] ?? $cache) : []) as $model) {
            $slug = \is_array($model) ? ($model['slug'] ?? $model['id'] ?? null) : null;
            if (\is_string($slug) && !str_contains($slug, 'review')) {
                $models[] = $slug;
            }
        }

        return array_values(array_unique($models)) ?: ['gpt-5.4'];
    }

    /**
     * Options that leave the CLI nothing but answering.
     *
     * @return array<string, mixed>
     */
    public static function options(string $id): array
    {
        return 'claude-code' === $id
            ? ['tools' => [], 'strict_mcp_config' => true]
            : ['sandbox' => 'read-only', 'skip_git_repo_check' => true];
    }

    /**
     * The CLIs take one prompt: the bridges send the last message of the owner only, so a conversation (code, then
     * "it fails with…") is written out into that one message.
     */
    public static function flattened(MessageBag $messages): MessageBag
    {
        $turns = $messages->withoutSystemMessage()->getMessages();
        if (\count($turns) <= 1) {
            return $messages;
        }
        $parts = [];
        foreach ($turns as $message) {
            $text = match (true) {
                $message instanceof AssistantMessage => "[Your earlier answer]\n".$message->asText(),
                $message instanceof UserMessage => "[Request]\n".$message->asText(),
                default => null,
            };
            if (null !== $text) {
                $parts[] = $text;
            }
        }
        $system = $messages->getSystemMessage();

        return new MessageBag(...array_filter([null !== $system ? Message::forSystem((string) $system->getContent()) : null, Message::ofUser(implode("\n\n", $parts))]));
    }
}
