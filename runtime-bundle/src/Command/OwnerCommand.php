<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Command;

use Cadenza\Runtime\Account\Accounts;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('cadenza:owner', 'Create the owner account or set a new password for it (asks for the password, it never appears on screen).')]
final class OwnerCommand
{
    public function __construct(private readonly Accounts $accounts)
    {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('The owner\'s e-mail; empty = the existing owner')] ?string $email = null): int
    {
        $owner = $this->accounts->owner();
        $email ??= $owner?->getEmail() ?? $io->ask('E-mail');
        $password = $io->askHidden(\sprintf('New password for %s (at least %d characters)', $email, Accounts::MIN_PASSWORD));
        if ($password !== $io->askHidden('Repeat it')) {
            $io->error('The passwords differ.');

            return 1;
        }
        try {
            $this->accounts->createOwner((string) $email, (string) $password);
        } catch (\InvalidArgumentException $e) {
            $io->error(['too_short' => 'Too short.', 'like_email' => 'Too similar to the e-mail.', 'invalid_email' => 'Not an e-mail address.'][$e->getMessage()] ?? $e->getMessage());

            return 1;
        }
        $io->success(\sprintf('%s can sign in at /_cadenza/login.', $email));

        return 0;
    }
}
