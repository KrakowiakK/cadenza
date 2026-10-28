# Findings in the betas (drafts to report)

Found while building Cadenza on Symfony AI 1.0.0-BETA1, MCP Bundle 1.0.0-BETA1, `mcp/sdk` 0.9 and Symfony UX 3.6.
Each entry is a draft for an issue: what we did, what happened, what we expected. **Not filed yet.**

## symfony/ai

### 1. Failover platform cannot fail over to a different model, nor on timeouts
- *Context*: `ai.platform.failover` with an Ollama Cloud platform and an OpenRouter platform.
- *Actual*: `FailoverPlatform::invoke()` calls every platform with the **same model name** (a model of provider A rarely
  exists at provider B), and it only catches exceptions thrown by `invoke()`; transport errors and timeouts surface
  later, when the `DeferredResult` is read, so they are never failed over.
- *Expected*: a model per platform in the failover configuration; failover on errors raised while reading the result.
- *Workaround in Cadenza*: two agents and a retry in `harness/src/Studio/Conductor.php`.

### 2. Strict tool argument resolution rejects common model output
- *Actual*: `ToolCallArgumentResolver` fails on `"9"` for an `int` parameter, on a JSON **string** for an `array`
  parameter, and on string items for `list<array<…>>` — all frequent with open-weight models.
- *Expected*: an opt-in lenient mode (numeric strings, JSON strings decoded for arrays), or a documented extension point.
- *Workaround*: `harness/src/Agent/LenientArgumentResolver.php` (decorates `ai.tool_call_argument_resolver`).

### 3. Generic platform: `base_url` with `/v1` yields `/v1/v1/chat/completions`
- *Actual*: OpenAI-compatible endpoints are usually documented as `https://host/v1`; the generic bridge appends
  `/v1/chat/completions` itself, so the natural value produces a 404.
- *Expected*: document that `base_url` excludes `/v1`, or normalise it.

### 4. `FallbackModelCatalog` referenced in config is not a service
- *Actual*: `model_catalog: 'Symfony\AI\Platform\Bridge\Generic\FallbackModelCatalog'` → "non-existent service"
  at compile time until the class is declared as a service by hand.
- *Expected*: the bundle registers the catalogs it documents.

## symfony/mcp-bundle

### 5. Client-only application: the recipe's MCP route breaks the container
- *Actual*: with only `mcp.clients` configured (no servers), `config/routes/mcp.yaml` from the recipe makes
  `cache:clear` fail with "Cannot load resource ".". Make sure there is a loader supporting the "mcp" type."
- *Expected*: the loader returns an empty collection when no server is configured (or the recipe is conditional).

### 6. HTTP transport needs PSR-17 factories that are not required
- *Actual*: both the HTTP server and the HTTP client fail at runtime with `Http\Discovery\Exception\NotFoundException`
  ("No PSR-17 … factory found") until `nyholm/psr7` is installed.
- *Expected*: a declared dependency (or a suggest + a clear error at container build time).

### 7. Prompt handler returning a string: the client only sees "Error while handling prompt"
- *Actual*: an `#[McpPrompt]` method returning a string is rejected server-side ("must return an array of messages",
  logged on the server), but the client receives only the generic message.
- *Expected*: accept a string as one user message, or send the reason to the client.

## symfony/ux — Toolkit

### 8. `ux:install` does not install the recipe's Composer dependencies
- *Actual*: installing shadcn components copies the templates; their `dependencies.composer`
  (`tales-from-a-dev/twig-tailwind-extra`, `twig/html-extra`) are not installed nor is the bundle registered, so the
  first render fails with "Unknown tailwind_classes filter".
- *Expected*: install (or at least print) the required packages.

### 9. Documented `trace` recipe is missing from the 3.6 release
- *Actual*: `ux:install trace --kit=shadcn` → "The recipe "trace" does not exist." (present on the main branch).

## 10. doctrine/migrations + SQLite: `make:migration` rebuilds a table without the columns it just added

**Where:** `doctrine/dbal` SQLite schema comparator (seen with DBAL 4, Doctrine ORM 3, SQLite 3.4x).

Adding two columns to an entity whose table has a foreign key with `ON DELETE CASCADE` generates `ALTER TABLE … ADD
COLUMN` for both, followed by a table rebuild (`CREATE TEMPORARY TABLE … / DROP TABLE / CREATE TABLE … / INSERT …`)
whose `CREATE TABLE` lacks the two new columns. Running it leaves the schema out of sync with the mapping
(`doctrine:schema:validate` fails). Workaround: keep only the `ADD COLUMN` statements.

## 11. Tool schemas: an associative `array` parameter becomes a JSON `array`, never an `object`

**Where:** `symfony/ai-platform` `Contract\JsonSchema` (tool parameters) and `mcp/sdk` (tool input schemas).

A tool parameter `array $values` meant as a map (`@param array<string, mixed>`) is described as `{"type": "array"}`;
the docblock description is dropped as well. Models then send a list, and the MCP server rejects the object the client
forwards ("Invalid type. Expected array"). The MCP SDK's `#[Schema(type: 'object')]` fixes the server side; Symfony
AI's `#[Schema]` has no `type`, so the agent side needs a DTO or tolerant input handling. Suggestion: map
`array<string, …>` to `object` with `additionalProperties`, keep the description, and allow a `type` override.
