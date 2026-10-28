<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Studio\SiteClient;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('guest_list', 'People invited to the members-only pages (pages with "access": "members"): who is on the list and who has signed in. To invite people, call with "add": [{"name": "...", "email": "..."}]. They sign in with a personal link the owner copies from the dashboard — never invent or promise links.', method: 'run')]
final class MemberTools
{
    public function __construct(private readonly SiteClient $site)
    {
    }

    /**
     * @param list<mixed> $add People to invite: [{"name": "Kasia Nowak", "email": "kasia@example.com"}]; empty = only show the list
     */
    public function run(array $add = []): string
    {
        $out = [];
        if ([] !== $add) {
            $people = array_values(array_filter(array_map(static fn (mixed $p): ?array => \is_array($p) ? $p : (\is_string($p) ? ['email' => $p] : null), $add)));
            $r = $this->site->addMembers($people);
            $out[] = \sprintf('Invited: %s.', implode(', ', $r['added'] ?? []) ?: 'nobody');
            if ([] !== ($r['errors'] ?? [])) {
                $out[] = 'Not added: '.implode('; ', $r['errors']).'.';
            }
            $out[] = 'Tell the owner their personal links are on the dashboard, section "Guests" (button "Copy link"), to send by text message, e-mail or chat.';
        }
        $list = $this->site->members();
        $out[] = \sprintf('%d people on the guest list%s', \count($list), [] === $list ? '.' : ':');
        foreach ($list as $m) {
            $out[] = \sprintf('  - %s <%s>, %s', $m['name'] ?? '—', $m['email'], null !== ($m['lastSignIn'] ?? null) ? 'signed in '.$m['lastSignIn'] : 'not signed in yet');
        }

        return implode("\n", $out);
    }
}
