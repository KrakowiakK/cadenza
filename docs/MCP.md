# MCP in Cadenza: three servers, three levels of trust

The website (`demo-app/` with `runtime-bundle/`) is an MCP server. Everything that changes the site goes through MCP,
including what Cadenza's own studio does. The split between "may propose" and "may publish" is made of servers and
tokens, so it holds whatever a model is told.

```
                         ┌──────────────── the owner's website ────────────────┐
 Claude Code, Cursor,    │  /_cadenza/mcp/guest    read 4 · sketch 1 · ask 1   │   guest token (derived, shown to
 Claude Desktop, any ───▶│  cadenza-site-guest                                 │   the owner in Connections)
 MCP client              │                                                     │
                         │  /_cadenza/mcp/agent    37 tools: read, drafts,     │
 Cadenza Studio ────────▶│  cadenza-site           collections, media, forms…  │   shared token (.env.local)
 (harness/, MCP client)  │                                                     │
                         │  /_cadenza/mcp/control  publish, discard, restore   │   shared token, used only after
 after the owner's ─────▶│  cadenza-site-control                               │   the owner clicked Approve
 click                   └─────────────────────────────────────────────────────┘
```

Configuration: [`demo-app/config/packages/mcp.yaml`](../demo-app/config/packages/mcp.yaml) (three servers of the Symfony
MCP Bundle, each with its own registry namespace). Guard:
[`McpGuardSubscriber`](../runtime-bundle/src/EventSubscriber/McpGuardSubscriber.php).

## 1. `guest`: an agent the owner brings along

```bash
./cadenza mcp        # prints the line for Claude Code and the JSON for other clients
```

```bash
claude mcp add --transport http cadenza-site http://127.0.0.1:8010/_cadenza/mcp/guest \
  --header "Authorization: Bearer czg_…"
```

Then, in that agent: *"Add a short FAQ before the contact form on my site, as a sketch."* About 15 seconds later the
sketch is in the owner's panel, signed *by Claude Code · MCP*, with the same Before / After, Approve and Reject as a
sketch of the studio. The panel asks for new sketches every few seconds, so it appears while the owner watches.

| Capability | What it does |
|---|---|
| tool `site_outline` | the live site: pages, node tree with ids, types and key props |
| tool `page_tree` | full JSON of one page |
| tool `node_get` | one node with all its props |
| tool `editor_guide` | the operation language and every block type with its props |
| tool `draft_propose` | a batch of operations becomes a draft; `agent` is the name the owner sees |
| tool `draft_status` | what the owner decided: `waiting`, `published` (with the version) or `rejected` |
| prompt `site-editor` | the editing guide as a prompt |
| resource `cadenza://site/outline` | the outline as a resource |

What a guest cannot do, by construction:

- **Publish.** `draft_publish` lives on another server; the guest token gets `401` there (tested in
  [`GuestAgentTest`](../demo-app/tests/Functional/GuestAgentTest.php) and in CI).
- **Read what visitors sent** (form answers, questions), the members' list, connected accounts or keys: those tools are
  on the `agent` server, which the guest token does not open either.
- **Touch a sketch of the studio**: it can revise only drafts it proposed itself.
- **Bury the owner**: at most twelve sketches of outside agents wait at once.
- **Break the page**: its operations pass the same validation as the studio's (typed props per block, image props only
  from the site's media library, links must lead somewhere).

The guest token is derived from the shared token (HMAC), so there is nothing extra to configure or store, and changing
the shared token revokes it.

## 2. `agent`: what the studio's agent uses

Cadenza Studio (`harness/`) is a Symfony AI agent whose tools are thin wrappers over this server
([`SiteClient`](../harness/src/Studio/SiteClient.php), an MCP client configured in
[`harness/config/packages/mcp.yaml`](../harness/config/packages/mcp.yaml)). Reading and drafting only:

- site: `site_outline`, `site_settings`, `page_tree`, `node_get`, `editor_guide`, `draft_propose`
- collections: `spreadsheet_analyze`, `collection_import`, `collection_create`, `collections_list`, `record_save`,
  `record_delete`, `records_find`, `records_pending`
- media and files: `media_import`, `media_list`, `media_stats`, `file_import`
- connectors: `connectors_list`, `import_start`, `import_status`, `connector_disconnect`
- forms and members: `forms_list`, `form_answers`, `members_list`, `members_add`
- blocks and widgets the agent writes: `block_define`, `blocks_list`, `widget_define`, `widgets_list`, `widget_code`,
  `widget_libraries`, `widget_scores`
- the site about itself: `site_text` (everything the published site says, as plain text), `site_stats`, `site_review` (a check without a model: dead links, placeholders, missing
  descriptions), `visitor_questions` (what visitors asked the assistant and the site could not answer)

## 3. `control`: the owner's decisions

`draft_publish`, `draft_discard`, `records_decide`, `drafts_waiting`, `history`, `version_restore`. Publishing, restoring a
version and publishing entries are called only from the HTTP endpoints behind the panel's buttons
([`ProposalController`](../harness/src/Controller/ProposalController.php), `HistoryController`, `RecordController`):
no tool of the agent reaches them. The one thing the agent may do here is drop a sketch the owner names
(`discard_sketches`). The proposal's life (drafted, sketched, approved, rejected) is a Symfony Workflow state machine.

## Try the guest server by hand

```bash
config=$(cd demo-app && php bin/console cadenza:guest-agent http://127.0.0.1:8010 --json)
url=$(echo "$config" | jq -r '.mcpServers["cadenza-site"].url')
auth=$(echo "$config" | jq -r '.mcpServers["cadenza-site"].headers.Authorization')

curl -s -X POST "$url" -H "Authorization: $auth" -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"curl","version":"1"}}}'
```

The same request against `…/mcp/control` answers `401`.
