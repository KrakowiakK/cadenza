<?php

declare(strict_types=1);

namespace App\Connection;

use App\Vault\Vault;
use App\Vault\VaultEnvVarProcessor;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * What the studio is connected to, and connecting it: checks a key with one request to the service, then keeps the
 * settings in the vault. Key values never come back out of here — only whether a setting is there and where from.
 */
final class Connections
{
    public function __construct(
        private readonly Vault $vault,
        private readonly Discovery $discovery,
        private readonly HttpClientInterface $http,
    ) {
    }

    /**
     * @return array{ready: bool, code: array<string, string>|null, model: array<string, mixed>, roles: array<string, array<string, mixed>>, services: array<string, array<string, mixed>>, found: list<array<string, mixed>>, providers: array<string, array<string, mixed>>}
     */
    public function status(bool $discover = true): array
    {
        $roles = [];
        foreach (Providers::ROLES as $role => [$baseUrl, $key, $model]) {
            $url = (string) $this->value($baseUrl);
            $provider = '' !== $url ? Providers::byBaseUrl($url) : null;
            $roles[$role] = [
                'provider' => $provider,
                'label' => null !== $provider ? (Providers::MODELS[$provider]['label'] ?? $provider) : null,
                'baseUrl' => $url,
                'model' => $this->value($model),
                'key' => null !== $this->value($key),
                'from' => $this->origin($key) ?? $this->origin($baseUrl),
            ];
        }
        $main = $roles['main'];
        $local = \in_array(parse_url((string) $main['baseUrl'], \PHP_URL_HOST), ['127.0.0.1', 'localhost'], true);
        $services = [];
        foreach (Providers::SERVICES as $id => $service) {
            $services[$id] = ['label' => $service['label'], 'for' => $service['for'], 'keyUrl' => $service['keyUrl'], 'connected' => null !== $this->value($service['setting']), 'from' => $this->origin($service['setting'])];
        }
        $providers = array_map(static fn (array $p): array => array_intersect_key($p, array_flip(['label', 'keyUrl', 'local', 'free'])), Providers::MODELS);

        $code = $this->value(Providers::JOBS['code']);
        $subscription = Subscriptions::parse($code);

        return [
            'ready' => '' !== $main['baseUrl'] && null !== $main['model'] && ($main['key'] || $local),
            'code' => null !== $subscription ? ['subscription' => $subscription[0], 'label' => Subscriptions::CLIS[$subscription[0]], 'model' => $subscription[1]] : null,
            'model' => $main,
            'roles' => $roles,
            'services' => $services,
            'found' => $discover ? $this->discovery->find() : [],
            'providers' => $providers,
        ];
    }

    /**
     * Tries a model API with a key: the models it offers, or why it does not work.
     *
     * @return array{ok: bool, models?: list<string>, error?: string}
     */
    public function test(string $provider, ?string $key, ?string $baseUrl = null): array
    {
        $preset = Providers::MODELS[$provider] ?? null;
        if (null === $preset) {
            return ['ok' => false, 'error' => 'Unknown service.'];
        }
        $baseUrl = rtrim($baseUrl ?: $preset['baseUrl'], '/');
        if (!preg_match('~^https?://~', $baseUrl)) {
            return ['ok' => false, 'error' => 'The address must start with http:// or https://.'];
        }
        $headers = null !== $key && '' !== $key ? ['Authorization' => 'Bearer '.$key] : [];
        try {
            if (isset($preset['check'])) {
                $this->http->request('GET', $baseUrl.$preset['check'], ['headers' => $headers, 'timeout' => 10])->toArray();
            }
            $data = $this->http->request('GET', $baseUrl.($preset['models'] ?? '/v1/models'), ['headers' => $headers, 'timeout' => 10])->toArray();
        } catch (\Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface $e) {
            $code = $e->getResponse()->getStatusCode();

            return ['ok' => false, 'error' => \in_array($code, [401, 403], true) ? 'This key does not work there (the service says it is not allowed).' : 'The service answered with an error ('.$code.').'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Nothing answers at '.$baseUrl.' ('.mb_substr($e->getMessage(), 0, 120).').'];
        }
        $models = array_values(array_filter(isset($data['models']) ? array_column($data['models'], 'name') : array_column($data['data'] ?? [], 'id'), 'is_string'));

        return ['ok' => true, 'models' => self::ordered($models, $preset['prefer'] ?? [])];
    }

    /**
     * Connects the studio's models to a provider: the given key (stored encrypted), or the environment variable that
     * has it, or none (a server on this computer). The roles we tested on that API get their models; the others use
     * the chosen one.
     *
     * @return array{ok: bool, error?: string, warning?: string}
     */
    public function useProvider(string $provider, ?string $key = null, ?string $environmentVariable = null, ?string $model = null, ?string $baseUrl = null): array
    {
        $preset = Providers::MODELS[$provider] ?? null;
        if (null === $preset) {
            return ['ok' => false, 'error' => 'Unknown service.'];
        }
        if (null !== $environmentVariable && (!\in_array($environmentVariable, $preset['env'] ?? [], true) || !Discovery::environment($environmentVariable))) {
            return ['ok' => false, 'error' => 'That environment variable is not set.'];
        }
        $resolvedKey = null !== $environmentVariable ? (string) ($_SERVER[$environmentVariable] ?? $_ENV[$environmentVariable] ?? getenv($environmentVariable)) : $key;
        if (empty($preset['local']) && 'custom' !== $provider && (null === $resolvedKey || '' === $resolvedKey)) {
            return ['ok' => false, 'error' => 'A key is needed.'];
        }
        $baseUrl = rtrim($baseUrl ?: $preset['baseUrl'], '/');
        $tested = $this->test($provider, $resolvedKey, $baseUrl);
        if (!$tested['ok']) {
            return $tested;
        }
        $roles = $preset['roles'] ?? [];
        $chosen = $model ?? $roles['main'] ?? ($tested['models'][0] ?? null);
        if (null === $chosen || '' === $chosen) {
            return ['ok' => false, 'error' => 'Choose a model.'];
        }
        if (null !== $model) {
            // The owner chose: that model does everything on this API.
            $roles = ['main' => $model];
        }
        $settings = [];
        foreach (Providers::ROLES as $role => [$urlName, $keyName, $modelName]) {
            $settings[$urlName] = $baseUrl;
            $settings[$modelName] = $roles[$role] ?? $roles['main'] ?? $chosen;
            $settings[$keyName] = null !== $environmentVariable ? null : ($key ?: null);
        }
        foreach (Providers::JOBS as $job => $name) {
            $settings[$name] = $roles[$job] ?? null;
        }
        if (null !== Subscriptions::parse($this->vault->get(Providers::JOBS['code']))) {
            // Games written with the owner's own plan stay so.
            unset($settings[Providers::JOBS['code']]);
        }
        $settings['CADENZA_LLM_LABEL'] = null;
        $this->vault->setMany($settings);
        if (null !== $environmentVariable) {
            foreach (Providers::ROLES as [, $keyName]) {
                $this->vault->point($keyName, $environmentVariable);
            }
        }

        return ['ok' => true] + array_filter(['warning' => 'lmstudio' === $provider ? $this->contextWarning($baseUrl, $chosen) : null]);
    }

    /**
     * LM Studio loads a model with a short context unless told otherwise; Cadenza's instructions and tools do not fit
     * in 8k tokens, and every request is then refused.
     */
    private function contextWarning(string $baseUrl, string $model): ?string
    {
        try {
            $info = $this->http->request('GET', $baseUrl.'/api/v0/models/'.rawurlencode($model), ['timeout' => 3])->toArray();
        } catch (\Throwable) {
            return null;
        }
        $loaded = (int) ($info['loaded_context_length'] ?? 0);
        if (\is_array($info) && \array_key_exists('capabilities', $info) && !\in_array('tool_use', (array) $info['capabilities'], true)) {
            // E.g. Gemma 3: it writes about the changes instead of making them.
            return 'LM Studio says this model cannot call tools, and Cadenza changes the site only through tools: it would talk about changes without making them. Choose a model with tool use (Qwen 3.5, Gemma 4, gpt-oss).';
        }

        return $loaded > 0 && $loaded < 32768
            ? \sprintf('LM Studio has this model loaded with a context of %d tokens; Cadenza needs at least 32768. Reload it with a longer context (LM Studio → the model → Context Length, or: lms load %s --context-length 65536).', $loaded, $model)
            : null;
    }

    /**
     * Connects a single-feature service (image search…) after checking the key.
     *
     * @return array{ok: bool, error?: string}
     */
    public function useService(string $id, ?string $key = null, ?string $environmentVariable = null): array
    {
        $service = Providers::SERVICES[$id] ?? null;
        if (null === $service) {
            return ['ok' => false, 'error' => 'Unknown service.'];
        }
        if (null !== $environmentVariable && (!\in_array($environmentVariable, $service['env'], true) || !Discovery::environment($environmentVariable))) {
            return ['ok' => false, 'error' => 'That environment variable is not set.'];
        }
        $resolvedKey = null !== $environmentVariable ? (string) ($_SERVER[$environmentVariable] ?? $_ENV[$environmentVariable] ?? getenv($environmentVariable)) : trim((string) $key);
        if ('' === $resolvedKey) {
            return ['ok' => false, 'error' => 'A key is needed.'];
        }
        try {
            $this->http->request('GET', $service['check'], ['headers' => ['Authorization' => str_replace('{key}', $resolvedKey, $service['auth'])], 'timeout' => 10])->getContent();
        } catch (\Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface $e) {
            return ['ok' => false, 'error' => \in_array($e->getResponse()->getStatusCode(), [401, 403], true) ? 'This key does not work there.' : 'The service answered with an error ('.$e->getResponse()->getStatusCode().').'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'The service does not answer ('.mb_substr($e->getMessage(), 0, 120).').'];
        }
        if (null !== $environmentVariable) {
            $this->vault->point($service['setting'], $environmentVariable);
        } else {
            $this->vault->set($service['setting'], $resolvedKey);
        }

        return ['ok' => true];
    }

    /**
     * Widget code written with the owner's own plan (a signed-in Claude Code or Codex), or by the main model (null).
     *
     * @return array{ok: bool, error?: string}
     */
    public function useForCode(?string $model): array
    {
        if (null === $model) {
            $this->vault->set(Providers::JOBS['code'], null);

            return ['ok' => true];
        }
        $parsed = Subscriptions::parse($model);
        if (null === $parsed) {
            return ['ok' => false, 'error' => 'Unknown subscription.'];
        }
        $signedIn = array_column($this->discovery->subscriptions(), 'signedIn', 'id');
        if (true !== ($signedIn[$parsed[0]] ?? false)) {
            return ['ok' => false, 'error' => Subscriptions::CLIS[$parsed[0]].' is not signed in on this computer.'];
        }
        $this->vault->set(Providers::JOBS['code'], $model);

        return ['ok' => true];
    }

    public function disconnectService(string $id): void
    {
        if (isset(Providers::SERVICES[$id])) {
            $this->vault->set(Providers::SERVICES[$id]['setting'], null);
        }
    }

    /**
     * A setting as the studio sees it (the environment, then the vault, then the .env files).
     */
    private function value(string $name): ?string
    {
        $env = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        $env = \is_string($env) && '' !== $env ? $env : null;
        if (null !== $env && !VaultEnvVarProcessor::fromDotenv($name)) {
            return $env;
        }

        return $this->vault->get($name) ?? $env;
    }

    /**
     * Where a setting comes from: "environment", "vault", "env:NAME" (the vault points to it), ".env" or null.
     */
    private function origin(string $name): ?string
    {
        $env = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
        $set = \is_string($env) && '' !== $env;
        if ($set && !VaultEnvVarProcessor::fromDotenv($name)) {
            return 'environment';
        }
        if (null !== $source = $this->vault->source($name)) {
            return $source;
        }

        return $set ? '.env' : null;
    }

    /**
     * @param list<string> $models
     * @param list<string> $prefer
     *
     * @return list<string>
     */
    public static function ordered(array $models, array $prefer): array
    {
        $rank = array_flip($prefer);
        usort($models, static fn (string $a, string $b): int => [$rank[$a] ?? \PHP_INT_MAX, $a] <=> [$rank[$b] ?? \PHP_INT_MAX, $b]);

        return $models;
    }
}
