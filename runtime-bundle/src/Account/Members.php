<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Account;

use Cadenza\Runtime\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

/**
 * People the owner lets into the members' pages (wedding guests, a club, a class). They have no password: each gets
 * a personal sign-in link, valid for a while and on any of their devices.
 */
final class Members
{
    /** An invitation from the owner stays valid for a month. */
    public const INVITE_LIFETIME = 30 * 86400;
    /** A link a member asks for again, by e-mail, for a day. */
    public const REQUEST_LIFETIME = 86400;

    public function __construct(
        private readonly EntityManagerInterface $em,
        // The site's firewall ("main") explicitly: links are also made outside a web request (agent tools, commands).
        #[Autowire(service: 'security.authenticator.login_link_handler.main')]
        private readonly LoginLinkHandlerInterface $links,
    ) {
    }

    /**
     * @return list<Account>
     */
    public function all(): array
    {
        return array_values(array_filter(
            $this->em->getRepository(Account::class)->findBy([], ['createdAt' => 'ASC']),
            static fn (Account $a): bool => !$a->isOwner(),
        ));
    }

    public function get(int $id): ?Account
    {
        $account = $this->em->find(Account::class, $id);

        return null !== $account && !$account->isOwner() ? $account : null;
    }

    public function byEmail(string $email): ?Account
    {
        $account = $this->em->getRepository(Account::class)->findOneBy(['email' => mb_strtolower(trim($email))]);

        return null !== $account && !$account->isOwner() ? $account : null;
    }

    /**
     * Adds a member, or renames one who is already there.
     */
    public function add(string $email, ?string $name = null): Account
    {
        if (false === filter_var(trim($email), \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('invalid_email');
        }
        $account = $this->em->getRepository(Account::class)->findOneBy(['email' => mb_strtolower(trim($email))]);
        if (null === $account) {
            $account = new Account($email, [Account::MEMBER], null !== $name && '' !== trim($name) ? trim($name) : null);
            $this->em->persist($account);
        } else {
            $account->grant(Account::MEMBER);
            if (null !== $name && '' !== trim($name)) {
                $account->rename(trim($name));
            }
        }
        $this->em->flush();

        return $account;
    }

    public function remove(Account $member): void
    {
        $this->em->remove($member);
        $this->em->flush();
    }

    public function link(Account $member, int $lifetime = self::INVITE_LIFETIME, ?string $target = null, ?Request $request = null): string
    {
        $url = $this->links->createLoginLink($member, $request, $lifetime)->getUrl();

        return null !== $target && str_starts_with($target, '/') && !str_starts_with($target, '//') ? $url.'&_target_path='.rawurlencode($target) : $url;
    }
}
