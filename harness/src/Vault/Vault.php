<?php

declare(strict_types=1);

namespace App\Vault;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The keys and settings of the services the studio uses (a model's API, image search…), set from the panel instead of
 * a .env file. Kept on this computer only: a JSON file whose values are encrypted with libsodium's secretbox under a
 * key of its own in var/ (both readable by the owner of the files only, neither ever committed). A setting can also
 * point to an environment variable the studio was started with ("use the OPENROUTER_API_KEY I already have"), so a
 * key found in the environment is not copied.
 */
final class Vault
{
    /** @var array<string, array{v?: string, env?: string}>|null */
    private ?array $entries = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/vault/%kernel.environment%')]
        private readonly string $directory,
    ) {
    }

    public function get(string $name): ?string
    {
        $entry = $this->entries()[$name] ?? null;
        if (isset($entry['env'])) {
            $value = $_SERVER[$entry['env']] ?? $_ENV[$entry['env']] ?? getenv($entry['env']);

            return \is_string($value) && '' !== $value ? $value : null;
        }
        if (!isset($entry['v'])) {
            return null;
        }
        $sealed = base64_decode($entry['v'], true);
        if (false === $sealed || \strlen($sealed) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $value = sodium_crypto_secretbox_open(substr($sealed, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($sealed, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key());

        return false === $value ? null : $value;
    }

    public function has(string $name): bool
    {
        return null !== $this->get($name);
    }

    /**
     * Where a setting comes from: "vault" (stored here), "env:NAME" (an environment variable it points to), or null.
     */
    public function source(string $name): ?string
    {
        $entry = $this->entries()[$name] ?? null;

        return match (true) {
            isset($entry['env']) => 'env:'.$entry['env'],
            isset($entry['v']) => 'vault',
            default => null,
        };
    }

    /**
     * @param array<string, string|null> $values name => value (null removes the setting)
     */
    public function setMany(array $values): void
    {
        $entries = $this->entries();
        foreach ($values as $name => $value) {
            if (null === $value || '' === $value) {
                unset($entries[$name]);
                continue;
            }
            $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $entries[$name] = ['v' => base64_encode($nonce.sodium_crypto_secretbox($value, $nonce, $this->key()))];
        }
        $this->save($entries);
    }

    public function set(string $name, ?string $value): void
    {
        $this->setMany([$name => $value]);
    }

    /**
     * The setting is read from this environment variable (its value is not copied).
     */
    public function point(string $name, string $environmentVariable): void
    {
        $entries = $this->entries();
        $entries[$name] = ['env' => $environmentVariable];
        $this->save($entries);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->entries());
    }

    /**
     * @return array<string, array{v?: string, env?: string}>
     */
    private function entries(): array
    {
        if (null === $this->entries) {
            $raw = is_file($this->directory.'/vault.json') ? file_get_contents($this->directory.'/vault.json') : false;
            $this->entries = false !== $raw && \is_array($decoded = json_decode($raw, true)) ? $decoded : [];
        }

        return $this->entries;
    }

    /**
     * @param array<string, array{v?: string, env?: string}> $entries
     */
    private function save(array $entries): void
    {
        $this->ensureDirectory();
        $file = $this->directory.'/vault.json';
        $tmp = $file.'.'.bin2hex(random_bytes(4));
        file_put_contents($tmp, json_encode($entries, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
        chmod($tmp, 0600);
        rename($tmp, $file);
        $this->entries = $entries;
    }

    private function key(): string
    {
        $file = $this->directory.'/vault.key';
        if (!is_file($file)) {
            $this->ensureDirectory();
            $key = sodium_crypto_secretbox_keygen();
            // Created exclusively, so two requests at once cannot each write a different key.
            $handle = @fopen($file, 'x');
            if (false !== $handle) {
                fwrite($handle, base64_encode($key));
                fclose($handle);
                chmod($file, 0600);
            }
        }
        $key = base64_decode(trim((string) file_get_contents($file)), true);
        if (false === $key || \SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== \strlen($key)) {
            throw new \RuntimeException(\sprintf('The vault key in "%s" is damaged; remove the vault directory and set the keys again.', $this->directory));
        }

        return $key;
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0700, true);
        }
    }
}
