<?php

declare(strict_types=1);

namespace App\Connection;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * What this computer already has, so the owner connects with one click instead of looking for keys: model servers
 * running here (Ollama, LM Studio), keys in the environment the studio was started with, and the coding CLIs the owner
 * is signed in to with a subscription (Claude Code, Codex). Nothing is read but whether they are there: no key value
 * leaves this class, and the CLIs keep their own sign-in.
 */
final class Discovery
{
    /** Where CLIs live besides the PATH of a web server (which is often short): the user's own first, as in their shell. */
    private const BIN_DIRS = ['~/.local/bin', '~/.npm-global/bin', '~/.cache/lm-studio/bin', '~/.lmstudio/bin', '/opt/homebrew/bin', '/usr/local/bin'];

    public function __construct(private readonly HttpClientInterface $http)
    {
    }

    /**
     * @return list<array{id: string, kind: string, label: string, how: string, env?: string, baseUrl?: string, models?: list<string>}>
     */
    public function find(): array
    {
        $found = [];
        foreach ($this->localServers() as $server) {
            $found[] = $server;
        }
        foreach (Providers::MODELS as $id => $provider) {
            foreach ($provider['env'] ?? [] as $name) {
                if (self::environment($name)) {
                    $found[] = ['id' => $id, 'kind' => 'model', 'label' => $provider['label'], 'how' => 'key in the environment ('.$name.')', 'env' => $name];
                    break;
                }
            }
        }
        foreach (Providers::SERVICES as $id => $service) {
            foreach ($service['env'] as $name) {
                if (self::environment($name)) {
                    $found[] = ['id' => $id, 'kind' => 'service', 'label' => $service['label'], 'how' => 'key in the environment ('.$name.')', 'env' => $name];
                    break;
                }
            }
        }
        foreach ($this->subscriptions() as $subscription) {
            $found[] = $subscription;
        }

        return $found;
    }

    /**
     * @return list<array{id: string, kind: string, label: string, how: string, baseUrl: string, models: list<string>}>
     */
    public function localServers(): array
    {
        $servers = [];
        $ollama = $this->models('http://127.0.0.1:11434/api/tags', static fn (array $d): array => array_column($d['models'] ?? [], 'name'));
        if (null !== $ollama) {
            $servers[] = ['id' => 'ollama', 'kind' => 'model', 'label' => Providers::MODELS['ollama']['label'], 'how' => 'running on this computer', 'baseUrl' => 'http://127.0.0.1:11434', 'models' => $ollama];
        }
        // LM Studio says its port (it is not always 1234).
        $ports = [1234];
        if (null !== $lms = $this->run('lms', ['server', 'status'])) {
            if (preg_match('~port (\d+)~i', $lms, $m)) {
                array_unshift($ports, (int) $m[1]);
            }
        }
        foreach (array_unique($ports) as $port) {
            $models = $this->models('http://127.0.0.1:'.$port.'/v1/models', static fn (array $d): array => array_column($d['data'] ?? [], 'id'));
            if (null !== $models) {
                $servers[] = ['id' => 'lmstudio', 'kind' => 'model', 'label' => Providers::MODELS['lmstudio']['label'], 'how' => 'running on this computer (port '.$port.')', 'baseUrl' => 'http://127.0.0.1:'.$port, 'models' => array_values(array_filter($models, static fn (string $m): bool => !str_contains($m, 'embed')))];
                break;
            }
        }

        return $servers;
    }

    /**
     * Coding CLIs signed in with the owner's own plan.
     *
     * @return list<array{id: string, kind: string, label: string, how: string, signedIn: bool, plan: bool, models: list<string>}>
     */
    public function subscriptions(): array
    {
        $found = [];
        if (null !== $status = $this->run('claude', ['auth', 'status'])) {
            $json = json_decode(substr($status, (int) strpos($status, '{')), true);
            $signedIn = \is_array($json) && true === ($json['loggedIn'] ?? false);
            $found[] = ['id' => 'claude-code', 'kind' => 'subscription', 'label' => 'Claude Code', 'how' => $signedIn ? 'signed in ('.('claude.ai' === ($json['authMethod'] ?? null) ? 'Claude plan' : 'API key').')' : 'installed, not signed in', 'signedIn' => $signedIn, 'plan' => $signedIn && 'claude.ai' === ($json['authMethod'] ?? null), 'models' => Subscriptions::models('claude-code')];
        }
        if (null !== $status = $this->run('codex', ['login', 'status'])) {
            $signedIn = (bool) preg_match('~logged in~i', $status) && !preg_match('~not logged in~i', $status);
            $found[] = ['id' => 'codex', 'kind' => 'subscription', 'label' => 'Codex', 'how' => $signedIn ? 'signed in ('.(preg_match('~chatgpt~i', $status) ? 'ChatGPT plan' : 'API key').')' : 'installed, not signed in', 'signedIn' => $signedIn, 'plan' => $signedIn && (bool) preg_match('~chatgpt~i', $status), 'models' => Subscriptions::models('codex')];
        }

        return $found;
    }

    public static function binary(string $name): ?string
    {
        foreach (self::BIN_DIRS as $dir) {
            $file = str_replace('~', self::home(), $dir).'/'.$name;
            if (is_file($file) && is_executable($file)) {
                return $file;
            }
        }

        return (new ExecutableFinder())->find($name);
    }

    /**
     * The name of the user the studio runs as (Claude Code finds its sign-in in the keychain by it).
     */
    public static function user(): string
    {
        $user = $_SERVER['USER'] ?? getenv('USER') ?: '';
        if ('' === $user && \function_exists('posix_getpwuid') && \function_exists('posix_geteuid')) {
            $user = (string) (posix_getpwuid(posix_geteuid())['name'] ?? '');
        }

        return $user;
    }

    /**
     * The home directory of the user the studio runs as (a web server often has no HOME set).
     */
    public static function home(): string
    {
        $home = $_SERVER['HOME'] ?? getenv('HOME') ?: '';
        if ('' === $home && \function_exists('posix_getpwuid') && \function_exists('posix_geteuid')) {
            $home = (string) (posix_getpwuid(posix_geteuid())['dir'] ?? '');
        }

        return $home;
    }

    /**
     * Whether the studio was started with this environment variable set (its value is not read here).
     */
    public static function environment(string $name): bool
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($value) && '' !== $value && !\App\Vault\VaultEnvVarProcessor::fromDotenv($name);
    }

    /**
     * @param \Closure(array<string, mixed>): list<string> $names
     *
     * @return list<string>|null null when nothing answers there
     */
    private function models(string $url, \Closure $names): ?array
    {
        try {
            $data = $this->http->request('GET', $url, ['timeout' => 1.5, 'max_duration' => 2.5])->toArray();
        } catch (\Throwable) {
            return null;
        }

        return array_values(array_filter($names($data), 'is_string'));
    }

    /**
     * @param list<string> $arguments
     */
    private function run(string $name, array $arguments): ?string
    {
        $binary = self::binary($name);
        if (null === $binary) {
            return null;
        }
        $process = new Process([$binary, ...$arguments], null, ['HOME' => self::home(), 'USER' => self::user(), 'PATH' => \dirname($binary).':'.(getenv('PATH') ?: '/usr/bin:/bin')]);
        $process->setTimeout(8);
        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        return $process->getOutput().$process->getErrorOutput();
    }
}
