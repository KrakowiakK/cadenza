<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Command;

use Cadenza\Runtime\EventSubscriber\McpGuardSubscriber;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('cadenza:guest-agent', 'How to connect an agent of your own (Claude Code, Claude Desktop, Cursor) to this site over MCP.')]
final class GuestAgentCommand
{
    public function __construct(
        #[Autowire('%cadenza.token%')]
        private readonly string $token,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Address of the site')] string $site = 'http://127.0.0.1:8010',
        #[Option('Print only the JSON for an MCP client\'s configuration')] bool $json = false,
    ): int {
        $url = rtrim($site, '/').'/_cadenza/mcp/guest';
        $guest = McpGuardSubscriber::guestToken($this->token);
        $config = json_encode(['mcpServers' => ['cadenza-site' => ['type' => 'http', 'url' => $url, 'headers' => ['Authorization' => 'Bearer '.$guest]]]], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
        if ($json) {
            $io->writeln((string) $config);

            return 0;
        }
        $io->title('Bring your own agent');
        $io->writeln([
            'Any MCP client can read this site and leave sketches. It cannot publish, read visitors\' answers',
            'or see your keys: its sketches wait in the panel for your decision.',
            '',
            '<info>Claude Code</info>',
            \sprintf('  claude mcp add --transport http cadenza-site %s --header "Authorization: Bearer %s"', $url, $guest),
            '',
            '<info>Claude Desktop, Cursor and other clients</info> (their MCP configuration)',
            (string) $config,
            '',
            'Then ask it, for example: "Add a short FAQ before the contact form on my site, as a sketch."',
        ]);

        return 0;
    }
}
