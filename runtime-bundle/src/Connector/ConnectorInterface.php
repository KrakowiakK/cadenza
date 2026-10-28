<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Connector;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A source of the owner's data in another service (Instagram, Google Photos, an RSS feed…). A connector only brings
 * data in and announces it with events (MediaImported); what the site does with it is up to the blocks.
 */
#[AutoconfigureTag('cadenza.connector')]
interface ConnectorInterface
{
    public function name(): string;

    public function label(): string;

    /**
     * What the agent and the owner should know: what it imports and how to connect.
     */
    public function description(): string;

    /**
     * Ways to bring data in: "oauth" (sign in, then sync), "file" (an export archive the owner uploads).
     *
     * @return list<'oauth'|'file'>
     */
    public function methods(): array;

    /**
     * Whether sign-in can be used on this installation (e.g. the app id and secret are configured).
     */
    public function oauthAvailable(): bool;

    public function authorizationUrl(string $redirectUri, string $state): string;

    /**
     * Exchanges the code from the provider's redirect for credentials.
     *
     * @return array{credentials: array<string, mixed>, account: string, expiresAt: ?\DateTimeImmutable}
     */
    public function finishAuthorization(string $code, string $redirectUri): array;

    /**
     * Imports from the connected account; $progress(done, total) after each item.
     *
     * @param array<string, mixed>       $credentials
     * @param callable(int, ?int): void $progress
     *
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function importFromAccount(array $credentials, ImportContext $context, callable $progress): array;

    /**
     * Imports from an export archive on disk.
     *
     * @param callable(int, ?int): void $progress
     *
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function importFromFile(string $path, ImportContext $context, callable $progress): array;

    /**
     * Fresh credentials before they expire, or null when nothing needs to be done.
     *
     * @param array<string, mixed> $credentials
     *
     * @return array{credentials: array<string, mixed>, expiresAt: ?\DateTimeImmutable}|null
     */
    public function refresh(array $credentials): ?array;
}
