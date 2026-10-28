<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Account;

use Cadenza\Runtime\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The site's accounts: creating the owner, changing passwords.
 */
final class Accounts
{
    public const MIN_PASSWORD = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function hasOwner(): bool
    {
        return null !== $this->owner();
    }

    public function owner(): ?Account
    {
        foreach ($this->em->getRepository(Account::class)->findBy([], ['id' => 'ASC']) as $account) {
            if ($account->isOwner()) {
                return $account;
            }
        }

        return null;
    }

    public function byEmail(string $email): ?Account
    {
        return $this->em->getRepository(Account::class)->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    /**
     * @return list<string> what is wrong with the password, empty when it is fine
     */
    public static function check(string $password, ?string $email = null): array
    {
        return array_values(array_filter([
            mb_strlen($password) < self::MIN_PASSWORD ? 'too_short' : null,
            null !== $email && '' !== $email && str_contains(mb_strtolower($password), mb_strtolower(strstr($email, '@', true) ?: $email)) ? 'like_email' : null,
        ]));
    }

    public function createOwner(string $email, string $password, ?string $name = null): Account
    {
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('invalid_email');
        }
        if ([] !== $problems = self::check($password, $email)) {
            throw new \InvalidArgumentException($problems[0]);
        }
        $account = $this->byEmail($email) ?? new Account($email, [Account::OWNER], $name);
        $account->grant(Account::OWNER);
        $account->setPassword($this->hasher->hashPassword($account, $password));
        $this->em->persist($account);
        $this->em->flush();

        return $account;
    }

    public function changePassword(Account $account, string $password): void
    {
        if ([] !== $problems = self::check($password, $account->getEmail())) {
            throw new \InvalidArgumentException($problems[0]);
        }
        $account->setPassword($this->hasher->hashPassword($account, $password));
        $this->em->flush();
    }

    public function isPasswordValid(Account $account, string $password): bool
    {
        return $this->hasher->isPasswordValid($account, $password);
    }

    public function signedIn(Account $account): void
    {
        $account->signedIn();
        $this->em->flush();
    }
}
