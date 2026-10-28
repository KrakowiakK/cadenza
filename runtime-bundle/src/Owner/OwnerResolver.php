<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Owner;

use Cadenza\Runtime\Entity\Account;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

/**
 * Decides whether the visitor is the site owner (sees the studio panel, sketches and the dashboard): someone signed in
 * with ROLE_OWNER. ?cadenza_guest=1 lets the owner look at the site as a visitor.
 */
final class OwnerResolver
{
    public function __construct(private readonly Security $security)
    {
    }

    public function isOwner(Request $request): bool
    {
        if ($request->query->getBoolean('cadenza_guest')) {
            return false;
        }

        return $this->security->isGranted(Account::OWNER);
    }

    public function account(): ?Account
    {
        $user = $this->security->getUser();

        return $user instanceof Account ? $user : null;
    }
}
