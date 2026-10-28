<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Account;

use Cadenza\Runtime\Entity\Account;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener]
final class LastLoginListener
{
    public function __construct(private readonly Accounts $accounts)
    {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof Account) {
            $this->accounts->signedIn($user);
        }
    }
}
