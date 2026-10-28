<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Mcp\Agent;

use Cadenza\Runtime\Account\Members;
use Cadenza\Runtime\Entity\Account;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;

/**
 * Who may open the members-only pages. Personal sign-in links are never given to the agent: the owner copies them on
 * the dashboard.
 */
final class MemberTools
{
    public function __construct(private readonly Members $members)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[McpTool(name: 'members_list', description: 'People invited to the members-only pages: name, e-mail, when they last signed in.', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function list(): array
    {
        return array_map(static fn (Account $m): array => ['id' => $m->getId(), 'name' => $m->getName(), 'email' => $m->getEmail(), 'lastSignIn' => $m->getLastLoginAt()?->format('Y-m-d H:i')], $this->members->all());
    }

    /**
     * @param list<array<string, string>> $people
     *
     * @return array<string, mixed>
     */
    #[McpTool(name: 'members_add', description: 'Invite people to the members-only pages (adds them to the guest list; names of people already there are updated). Their personal links are on the owner\'s dashboard.')]
    public function add(
        #[Schema(description: 'e.g. [{"name": "Kasia Nowak", "email": "kasia@example.com"}]', items: ['type' => 'object'])]
        array $people,
    ): array {
        $added = [];
        $errors = [];
        foreach ($people as $person) {
            try {
                $added[] = $this->members->add((string) ($person['email'] ?? ''), $person['name'] ?? null)->getEmail();
            } catch (\InvalidArgumentException) {
                $errors[] = \sprintf('"%s" is not an e-mail address', $person['email'] ?? '');
            }
        }

        return ['ok' => [] === $errors, 'added' => $added, 'errors' => $errors, 'total' => \count($this->members->all()), 'links' => '/_cadenza/dashboard#members'];
    }
}
