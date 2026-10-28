<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Connector;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Seals connector credentials (tokens) with libsodium, using a key derived from the application secret.
 */
final class CredentialVault
{
    private string $key;

    public function __construct(#[Autowire('%kernel.secret%')] string $secret)
    {
        $this->key = sodium_crypto_generichash('cadenza-connections|'.$secret, '', \SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    /**
     * @param array<string, mixed> $credentials
     */
    public function seal(array $credentials): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox(json_encode($credentials, \JSON_THROW_ON_ERROR), $nonce, $this->key));
    }

    /**
     * @return array<string, mixed>
     */
    public function open(string $sealed): array
    {
        $raw = base64_decode($sealed, true) ?: '';
        $plain = sodium_crypto_secretbox_open(substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key);
        if (false === $plain) {
            throw new \RuntimeException('Cannot open the stored credentials (was APP_SECRET changed?). Connect again.');
        }

        return json_decode($plain, true, flags: \JSON_THROW_ON_ERROR);
    }
}
