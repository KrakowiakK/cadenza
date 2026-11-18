# Upstream findings, verified

Verification of the drafts in `docs/UPSTREAM_ISSUES.md` (findings 1-7 and 11-13; 8-10 not in scope).
Checked on 2026-11-18. Nothing was filed.

**Compared against**

| What | Version |
| --- | --- |
| Installed (`harness/vendor`) | `symfony/ai-*` and `symfony/mcp-bundle` v1.0.0-BETA1 (tag of 2026-10-26), `mcp/sdk` 0.9.0, PHP 8.5.12 |
| `symfony/ai` default branch | `main` at `56d6d58` (2026-11-17) |
| `modelcontextprotocol/php-sdk` default branch | `main` at `3d2e831` (2026-11-16); latest release v0.10.0 (2026-11-10) |

**Summary**

| # | Verdict | Target repo | Short reason |
| --- | --- | --- | --- |
| 1 | ALREADY REPORTED | symfony/ai | Both halves have open issues: #1716 (model per platform), #2944 (errors raised when the result is read) |
| 2 | FILE (feature request, corrected) | symfony/ai | Reproduced; no issue asks for lenient coercion. One sub-claim of the draft is wrong |
| 3 | FILE (narrowed) | symfony/recipes | Convention is deliberate (symfony/ai#2240), but the recipe's own example URL ends in `/v1` |
| 4 | NOT CONFIRMED | - | `model_catalog` takes a service ID; `FallbackModelCatalog` is already the default. Configuration mistake on our side |
| 5 | FILE | symfony/ai (mcp-bundle) | Reproduced; same code on `main`; no issue found |
| 6 | FIXED UPSTREAM | symfony/ai (mcp-bundle) | symfony/ai#2828, merged 2026-10-27 (one day after BETA1) |
| 7 | NOT CONFIRMED (as a bug) | modelcontextprotocol/php-sdk | Behaviour is real but documented: supported return formats and the generic error are both in the SDK docs |
| 11 | FILE (split in two, narrowed) | symfony/ai; SDK part FIXED UPSTREAM | Map-as-array and dropped description reproduced; SDK maps `array<string, V>` to an object since v0.10.0 |
| 12 | ALREADY REPORTED | symfony/ai | symfony/ai#2544 (open); partly changed by #2891 |
| 13 | FILE | symfony/ai | Catalog rejection reproduced; same catalog on `main`; only the general issue #1692 exists |

How duplicates were searched: `gh search issues --repo <repo> --include-prs "<keywords>"` (open and closed), several keyword
sets per finding. GitHub search is keyword based, so a report phrased very differently could have been missed; a quick
manual look before filing is still worth it.

---

## 1. Failover platform: same model for every platform, no failover on read-time errors

**Verdict: ALREADY REPORTED.** Target: symfony/ai. Do not file.

- `symfony/ai-failover-platform` is **not installed** in Cadenza (`harness/vendor/symfony` has no failover package; only
  the bundle config node `harness/vendor/symfony/ai-bundle/config/platform/failover.php` exists). Code read from the
  upstream tag, not reproduced.
- Code: `src/platform/src/Bridge/Failover/FailoverPlatform.php` at v1.0.0-BETA1, identical on `main`:
  - lines 52-55: `invoke()` passes the same `$model` to every platform;
  - lines 62-89: `do()` only catches what `$func($platform)` throws, i.e. what `invoke()` throws; a `DeferredResult`
    is returned unread.
- Existing reports:
  - Model per platform: https://github.com/symfony/ai/issues/1716 (open), earlier https://github.com/symfony/ai/issues/1401
    (closed), PR https://github.com/symfony/ai/pull/1808 (open, "Add support for model override"), PR
    https://github.com/symfony/ai/pull/1864 (open).
  - Errors raised when the result is read: https://github.com/symfony/ai/issues/2944 (open, 2026-11-14). It describes 429
    and 5xx; timeouts go through the same path. If anything, add a comment there that transport timeouts are affected too.
  - Direction of travel: https://github.com/symfony/ai/issues/1953 (move failover to a `FailoverProvider`).
- Cadenza workaround still present: `harness/src/Studio/Conductor.php` (fallback agent, lines 548-582).

## 2. Strict tool argument resolution

**Verdict: FILE, as a feature request, with the draft corrected.** Target: symfony/ai (Agent).

- Code: `harness/vendor/symfony/ai-agent/src/Toolbox/ToolCallArgumentResolver.php`, identical to `main`:
  - line 99 resolves the PHP parameter type, lines 110-116 turn it into a type string;
  - lines 118-120: the only coercion is int to float;
  - lines 122-128: the Serializer denormalizes strictly and failures become `InvalidToolCallArgumentsException`.
- **Reproduced** with a script against `harness/vendor` (default `new ToolCallArgumentResolver()`):

  | Parameter | Argument sent | Result |
  | --- | --- | --- |
  | `int $n` | `"9"` | `InvalidToolCallArgumentsException`: Data expected to be of type "int" ("string" given) |
  | `bool $b` | `"true"` | same, "bool" |
  | `float $f` | `"1.5"` | same, "float" |
  | `int[] $ids` | `["1","2"]` | same, "int" |
  | `array $tags` | `"[\"a\",\"b\"]"` (JSON string) | resolver passes the string through; the tool call then fails with a PHP `TypeError` |
  | `array $rows` (`@param list<array{...}>`) | `["{\"name\":\"a\"}"]` | resolver passes it through and the call **succeeds**; the tool receives strings |

- Corrections to the draft:
  - "fails on string items for `list<array<...>>`" is **not true**: the resolver does not look at the docblock and passes
    the list through.
  - A JSON string for an `array` parameter is not rejected by the resolver either; it fails later as a `TypeError` in
    the tool invocation, wrapped by `ToolExecutionException::executionFailed()`.
  - "or a documented extension point": the extension point exists (`ToolCallArgumentResolverInterface`, service
    `ai.tool_call_argument_resolver`), so ask only for the lenient mode.
- Upstream context: strictness is known and the chosen answer so far is to report the error back to the model
  (https://github.com/symfony/ai/issues/2137, fixed by https://github.com/symfony/ai/pull/2365); int to float widening was
  accepted as a fix (https://github.com/symfony/ai/issues/2136). No issue or PR about numeric strings, boolean strings or
  JSON-encoded arrays was found. Expect this to be judged as a feature request, not a bug.
- Cadenza workaround still present: `harness/src/Agent/LenientArgumentResolver.php`.

### Ready-to-paste issue

**Title:** [Agent] Opt-in lenient coercion of tool call arguments (numeric strings, boolean strings, JSON-encoded arrays)

**Body:**

````markdown
### Description

`ToolCallArgumentResolver` denormalizes tool call arguments strictly. Smaller and open-weight models regularly send
scalar arguments as strings (`"9"` for an `int`, `"true"` for a `bool`) or a JSON-encoded string for an `array`
parameter. Each of these fails the tool call. Since #2365 the model is told why and can retry, but that costs a round
trip per call, and some models repeat the same form.

This is a feature request for an opt-in lenient mode; the strict default is fine.

### How to reproduce

`symfony/ai-agent` v1.0.0-BETA1 (the resolver is unchanged on `main`), PHP 8.5.

```php
use Symfony\AI\Agent\Toolbox\ToolCallArgumentResolver;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class Demo
{
    public function count(int $n): string { return (string) $n; }
    public function flag(bool $b): string { return 'x'; }
    /** @param int[] $ids */
    public function ids(array $ids): string { return 'x'; }
    public function tags(array $tags): string { return 'x'; }
}

$resolver = new ToolCallArgumentResolver();
$cases = [
    ['count', ['n' => '9']],
    ['flag', ['b' => 'true']],
    ['ids', ['ids' => ['1', '2']]],
    ['tags', ['tags' => '["a","b"]']],
];
foreach ($cases as [$method, $arguments]) {
    $tool = new Tool(new ExecutionReference(Demo::class, $method), $method, 'demo');
    $resolved = $resolver->resolveArguments($tool, new ToolCall('1', $method, $arguments));
    (new Demo())->$method(...$resolved);
}
```

### Actual

- `count`, `flag`, `ids`: `InvalidToolCallArgumentsException`, e.g.
  `Invalid value for parameter "n" of tool "count": "Data expected to be of type "int" ("string" given)."`
- `tags`: the resolver returns the string unchanged and the tool invocation throws
  `TypeError: Demo::tags(): Argument #1 ($tags) must be of type array, string given`.

### Expected

An opt-in way (constructor flag, bundle option, or a shipped decorator) to accept the unambiguous cases before
denormalizing: integer and float strings for `int`/`float`, `"true"`/`"false"` for `bool`, and a JSON string that
decodes to an array for an `array` parameter. Today this needs a custom decorator of
`ToolCallArgumentResolverInterface`.
````

## 3. Generic platform: `base_url` with `/v1`

**Verdict: FILE, narrowed to the recipe.** Target: **symfony/recipes** (not symfony/ai).

- Code (installed, behaviour identical on `main`):
  - `harness/vendor/symfony/ai-generic-platform/Factory.php` lines 42-43 and 74-75: `completionsPath = '/v1/chat/completions'`,
    `embeddingsPath = '/v1/embeddings'`;
  - `harness/vendor/symfony/ai-generic-platform/Completions/ModelClient.php` line 47 (`rtrim($baseUrl, '/')`) and line 86
    (`$this->baseUrl.$this->path`);
  - `harness/vendor/symfony/ai-bundle/config/platform/generic.php` lines 31-32: same defaults, overridable with
    `completions_path` / `embeddings_path`.
- **Reproduced** with `MockHttpClient` against `harness/vendor`:
  `https://api.example.com/v1` -> `POST https://api.example.com/v1/v1/chat/completions`;
  `https://api.example.com` -> `POST https://api.example.com/v1/chat/completions`.
- Upstream already discussed and decided the convention: https://github.com/symfony/ai/issues/2240 (closed; it even
  mentions the silent `/v1/v1`), resolved by https://github.com/symfony/ai/pull/2243, and `UPGRADE.md` on `main` states
  "the API version must **not** be included in `baseUrl`, it is part of the path instead". So the draft as written
  ("document or normalise") would be answered with #2240. **Do not file it against symfony/ai.**
- What is still wrong, and not reported: the official recipe contradicts that convention.
  `symfony/recipes`, `symfony/ai-generic-platform/0.1/manifest.json` adds the commented `.env` example
  `GENERIC_BASE_URL=https://api.example.com/v1` (present since the first version of the recipe, commit `a5d6b412`;
  unchanged by recipes PRs #1527 and #1613). Uncommenting the recipe's config and following that example gives
  `/v1/v1/chat/completions`. Searched symfony/recipes issues and PRs for `GENERIC_BASE_URL`, `ai-generic-platform`,
  `generic platform`: nothing about the URL.
- No Cadenza workaround file; our env values simply omit `/v1`.

### Ready-to-paste issue (symfony/recipes)

**Title:** [symfony/ai-generic-platform] `GENERIC_BASE_URL` example ends with `/v1`, which results in `/v1/v1/chat/completions`

**Body:**

````markdown
### Description

The recipe of `symfony/ai-generic-platform` (0.1) adds this example to `.env`:

```
# GENERIC_BASE_URL=https://api.example.com/v1
```

The generic bridge expects a base URL without the API version: `Generic\Factory` defaults to
`completionsPath: '/v1/chat/completions'` and `embeddingsPath: '/v1/embeddings'`, and the convention was confirmed in
symfony/ai#2240 ("the API version must not be included in `baseUrl`", `UPGRADE.md`). With the example value, requests go
to `/v1/v1/chat/completions` and the endpoint answers 404.

### How to reproduce

`symfony/ai-generic-platform` and `symfony/ai-bundle` v1.0.0-BETA1.

```yaml
# config/packages/ai_generic_platform.yaml (the recipe's block, uncommented)
ai:
    platform:
        generic:
            default:
                base_url: '%env(GENERIC_BASE_URL)%'
```

```
# .env, following the recipe's example
GENERIC_BASE_URL=https://api.example.com/v1
```

Or without the bundle:

```php
$platform = Symfony\AI\Platform\Bridge\Generic\Factory::createPlatform('https://api.example.com/v1', 'key', $httpClient);
$platform->invoke('some-model', new MessageBag(Message::ofUser('Hello')))->asText();
```

### Actual

`POST https://api.example.com/v1/v1/chat/completions`

### Expected

The example shows a value that works with the defaults: `GENERIC_BASE_URL=https://api.example.com`.

### Possible fix

Drop `/v1` from the `env` example in `symfony/ai-generic-platform/0.1/manifest.json`.
````

## 4. `FallbackModelCatalog` referenced in config is not a service

**Verdict: NOT CONFIRMED.** Do not file.

- The error is real, but it is what the option is specified to do. `model_catalog` given as a string is "short for the
  `service` option", a **service ID** (`harness/vendor/symfony/ai-bundle/config/platform/model_catalog.php`, the
  `beforeNormalization()->ifString()` block and the `service` node: "Service ID of a custom model catalog to use instead
  of the bridge one"). `AiBundle::createModelCatalog()` turns it into `new Reference($config['service'])`
  (`harness/vendor/symfony/ai-bundle/src/AiBundle.php` lines 3158-3159). A class name that is not registered as a service
  is a non-existent service, as for any Symfony service reference.
- `FallbackModelCatalog` is **already the default** of the generic platform: `ai.platform.model_catalog.generic` is
  registered with that class (`harness/vendor/symfony/ai-bundle/config/services.php` line 147) and used when the option
  is absent (`AiBundle.php` line 790). The same on `main`.
- The bundle documentation does not claim otherwise: its only FQCN example
  (`docs/bundles/ai-bundle.rst` lines 201-215, `Symfony\AI\Platform\Bridge\Generic\ModelCatalog`) declares that class
  under `services:` right below.
- What is actually true for Cadenza: the three `model_catalog: 'Symfony\AI\Platform\Bridge\Generic\FallbackModelCatalog'`
  lines in `harness/config/packages/ai_generic_platform.yaml` (lines 9, 13, 18) and the service declaration in
  `harness/config/services.yaml` (line 33) are redundant and can be removed. Not changed here (out of scope).
- Read from code; not reproduced by compiling a container.

## 5. Client-only application: the recipe's MCP route import fails

**Verdict: FILE.** Target: symfony/ai (MCP Bundle).

- Code: `harness/vendor/symfony/mcp-bundle/src/McpBundle.php`
  - lines 120-122: early return when neither servers nor clients are configured;
  - lines 129-131: `configureServers()` runs only when `servers` is not empty;
  - lines 609-627: `configureRouting()` (called from `configureServers()`, line 325) is the only place that registers
    `mcp.server.route_loader` with the `routing.loader` tag.
  So without a server there is no loader for `type: mcp`. `main` has the same structure (`McpBundle.php` lines 119-135 and
  614-632).
- The import comes from the recipe (`symfony/recipes`, `symfony/mcp-bundle/0.13/config/routes/mcp.yaml`: `resource: .`,
  `type: mcp`) and from the bundle docs (`docs/bundles/mcp-bundle.rst` lines 24-31, stated for the bundle in general, before
  the server/client sections).
- **Reproduced** with a minimal kernel (FrameworkBundle + McpBundle from `harness/vendor`, routes importing `.` with type
  `mcp`):
  - one server configured: OK, route `_mcp_endpoint_default`;
  - only `mcp.clients` configured: `LoaderLoadException: Cannot load resource ".". Make sure there is a loader supporting the "mcp" type.`;
  - no `mcp` configuration at all: same exception.
- Searched symfony/ai and symfony/recipes issues and PRs (`mcp route loader`, `RouteLoader mcp`, `Cannot load resource mcp`,
  `MCP Bundle client only`, `mcp-bundle`): nothing.
- Cadenza workaround: `harness/config/routes/mcp.yaml` was deleted (the directory only has `framework.yaml`,
  `security.yaml`, `web_profiler.yaml`; `harness/symfony.lock` still lists the file for the recipe).

### Ready-to-paste issue

**Title:** [MCP Bundle] Importing routes with `type: mcp` fails when no MCP server is configured (client-only application)

**Body:**

````markdown
### Description

The recipe and the documentation add a route import for the bundle:

```yaml
# config/routes/mcp.yaml
mcp:
    resource: .
    type: mcp
```

The loader for the `mcp` type is only registered when at least one server is configured
(`McpBundle::configureRouting()`, called from `configureServers()`). An application that only uses `mcp.clients`, or
that has not configured the bundle yet, has no such loader and the router cannot be built.

### How to reproduce

`symfony/mcp-bundle` v1.0.0-BETA1 (same code on `main`), `mcp/sdk` 0.9.0, PHP 8.5.

```yaml
# config/packages/mcp.yaml
mcp:
    clients:
        app:
            servers:
                site:
                    transport: http
                    url: 'http://localhost/mcp'
```

Keep the recipe's `config/routes/mcp.yaml` and run `bin/console cache:clear` (or `debug:router`).

### Actual

```
Symfony\Component\Config\Exception\LoaderLoadException:
Cannot load resource ".". Make sure there is a loader supporting the "mcp" type.
```

The same happens with an empty `mcp:` configuration.

### Expected

The import loads an empty route collection when no server exposes HTTP, so the recipe's files work for server, client
and mixed applications.

### Possible fix

Register `mcp.server.route_loader` (with an empty server list) whenever the bundle is loaded, not only inside `configureServers()`.
````

## 6. HTTP transport needs PSR-17 factories that are not required

**Verdict: FIXED UPSTREAM.** Target would have been symfony/ai (MCP Bundle). Do not file.

- Installed: `harness/vendor/symfony/mcp-bundle/composer.json` requires `php-http/discovery` but no PSR-17
  implementation (`nyholm/psr7` is only in `require-dev`); `config/services.php` lines 14 and 26 register
  `Http\Discovery\Psr17Factory` directly. Read from code, not reproduced (it would need removing `nyholm/psr7`).
- Fixed by https://github.com/symfony/ai/pull/2828 "[MCP Bundle] Let php-http/discovery install a PSR-17 implementation",
  merged 2026-10-27 (commit `d7667c6`), one day after the BETA1 tag. On `main`:
  - `src/mcp-bundle/composer.json` line 19 requires `psr/http-factory-implementation: *`, so the discovery Composer plugin
    installs `nyholm/psr7` when nothing provides it;
  - `src/mcp-bundle/src/Http/Psr17Factory.php` throws
    `No PSR-17 factories have been found. Try running "composer require php-http/discovery psr/http-factory-implementation:*".`
  The package requirement covers the client transport as well; `Client/TransportFactory::http()` on `main` additionally
  explains a missing PSR-18 client.
- Cadenza workaround still present: `nyholm/psr7` in `harness/composer.json` (line 14) and `demo-app/composer.json`
  (line 17). It can go once a release containing #2828 is installed.

## 7. Prompt handler returning a string

**Verdict: NOT CONFIRMED as a bug (documented behaviour).** Repo it belongs to: modelcontextprotocol/php-sdk, not the
bundle. Do not file as a bug.

- The described behaviour is real. Code in `harness/vendor/mcp/sdk` (0.9.0):
  - `src/Capability/Formatter/PromptResultFormatter.php` lines 47-49: a non-array result throws
    `RuntimeException('Prompt generator method must return an array of messages.')`;
  - `src/Server/Handler/Request/GetPromptHandler.php` line 71 calls the formatter, lines 78-81 forward the message only
    for `PromptGetException`, lines 88-91 log the real message and answer `Error::forInternalError('Error while handling prompt')`.
  On `main` the files moved to `src/Server/...` with the same logic (formatter lines 47-49; handler lines 92-101).
- **Reproduced** the formatter part: `(new PromptResultFormatter())->format('Write a poem')` throws
  `Mcp\Exception\RuntimeException`, which is not a `PromptGetException`. The client-side message was read from the handler
  code, not observed over a transport.
- Why it is not a bug: `docs/servers/prompts.md` (in 0.9.0 and on `main`) lists the "Supported Return Formats" (arrays of
  messages, a single message array, a `PromptMessage`, `user`/`assistant` keys; no plain string) and states under "Error
  Handling" that any exception other than `PromptGetException` is "converted to JSON-RPC error response, but with a
  generic error message". Hiding internal exception messages from clients is intended.
- No existing issue or PR about string results was found in php-sdk (`prompt string`, `PromptResultFormatter`,
  `prompt return`). If wanted, the only fileable form is a small feature request ("accept a string as one user message"),
  clearly labelled as such; it was not drafted here because the present behaviour is documented.
- Cadenza: the prompt handlers (`runtime-bundle/src/Mcp/Agent/SiteTools.php`, `runtime-bundle/src/Mcp/Guest/GuestTools.php`)
  exist; the draft names no workaround file.

## 11. Tool schemas: map parameters described as JSON arrays

**Verdict: FILE for symfony/ai, split into two narrow issues (11a, 11b). The mcp/sdk part is FIXED UPSTREAM. The
LM Studio / gpt-oss part was not verified and is left out of the issues.**

- Code (installed, identical on `main`):
  - `harness/vendor/symfony/ai-platform/src/Contract/JsonSchema/Describer/TypeInfoDescriber.php` lines 132-137: every PHP
    `array` becomes `['type' => 'array']`, with `items` only when the value type yields a schema; the key type is ignored;
  - `harness/vendor/symfony/ai-platform/src/Contract/JsonSchema/Describer/MethodDescriber.php` line 53: the description
    regex is `/@param\s+\S+\s+\$name\s+.../`. `\S+` cannot match a type that contains a space;
  - `harness/vendor/symfony/ai-platform/src/Contract/JsonSchema/Attribute/Schema.php`: the constructor has no `type`
    argument (confirmed by reflection).
- **Reproduced** with `(new Factory())->buildParameters()` against `harness/vendor`:

  | Parameter | Schema produced |
  | --- | --- |
  | `@param array<string, mixed> $values Map of ...` | `{"type":"array"}`, no description |
  | `@param array<string, string> $labels Map of ...` | `{"type":"array","items":{"type":"string"}}`, no description |
  | `@param list<string> $tags A list of tags` | `{"type":"array","items":{"type":"string"},"description":"A list of tags"}` |
  | `@param array{name: string, age: int} $shape A shape` | array of `anyOf` integer/string, no description |
  | `array $bare` (no docblock) | `{"type":"array"}` |

- Corrections to the draft:
  - "the docblock description is dropped" is true **only when the type contains whitespace** (`array<string, mixed>`,
    `array{name: string, ...}`); `list<string>` keeps it. This is a separate, small bug (11b).
  - `{"type":"array"}` without `items` is valid JSON Schema. The claim that gpt-oss in LM Studio answers HTTP 400 because of
    it could not be verified here (no such request was made) and is not included in the issues below.
  - mcp/sdk: in 0.9.0 `array<string, mixed>` does give `{"type":"array","items":{}}` (reproduced with
    `Mcp\Capability\Discovery\SchemaGenerator`). On `main` and in **v0.10.0** it gives
    `{"type":"object","additionalProperties":...}` (`src/Server/Capability/Discovery/SchemaGenerator.php` lines 1034-1043;
    https://github.com/modelcontextprotocol/php-sdk/pull/643, merged 2026-11-03). Nothing to file there.
- Related upstream work, to be referenced in the issue:
  - https://github.com/symfony/ai/pull/2923 (open): array **shapes** become objects; it says explicitly that
    "`array<string, T>` maps stay as they are, since an `additionalProperties` schema isn't supported everywhere" (see the
    comment in `ValidatorConstraintsDescriber::describeAll()`, line 496 on `main`). Expect push-back on
    `additionalProperties`; the issue below asks for the type, not for a specific keyword.
  - https://github.com/symfony/ai/pull/2915 (open, by a maintainer): replaces the describers, `MethodDescriber` included,
    with `symfony/json-schema`. Whether that PR fixes 11a/11b was **not verified** (it was not run). If it is merged first,
    re-test before filing.
  - No issue found for maps, for the dropped description, or for a `type` override on `#[Schema]`.
- Cadenza workaround still present: `harness/src/Agent/Schema/ListItemsToolFactory.php` (adds `items` to arrays).

### Ready-to-paste issue 11a

**Title:** [Platform] A tool parameter typed `array<string, V>` is described as a JSON array

**Body:**

````markdown
### Description

`TypeInfoDescriber` describes every PHP `array` as `{"type": "array"}` and ignores the key type. A parameter documented
as a string-keyed map therefore tells the model to send a list, although the tool expects an object. For
`array<string, mixed>` the schema is a bare `{"type": "array"}`.

#2923 changes array shapes to objects and leaves `array<string, T>` as it is; this issue is about that remaining case.

### How to reproduce

`symfony/ai-platform` v1.0.0-BETA1 (`TypeInfoDescriber` is unchanged on `main`), `phpstan/phpdoc-parser` installed, PHP 8.5.

```php
use Symfony\AI\Platform\Contract\JsonSchema\Factory;

final class SaveFields
{
    /**
     * @param array<string, string> $labels Map of field name to label
     * @param array<string, mixed>  $values Map of field name to value
     */
    public function __invoke(array $labels, array $values): string
    {
        return 'ok';
    }
}

echo json_encode((new Factory())->buildParameters(SaveFields::class, '__invoke'), \JSON_PRETTY_PRINT);
```

### Actual

```json
{
    "type": "object",
    "properties": {
        "labels": {"type": "array", "items": {"type": "string"}},
        "values": {"type": "array"}
    },
    "required": ["labels", "values"],
    "additionalProperties": false
}
```

### Expected

A string-keyed map is described as `"type": "object"` (with the value type where the target platform accepts it), or
there is a way to override the type of a single parameter; `#[Schema]` currently has no `type` argument.
````

### Ready-to-paste issue 11b

**Title:** [Platform] `@param` description of a tool parameter is dropped when its type contains a space

**Body:**

````markdown
### Description

`MethodDescriber::fromParameter()` extracts the description with

```php
'/@param\s+\S+\s+\$'.preg_quote($parameter->getName(), '/').'\s+((.*)(?=\*)|.*)/'
```

`\S+` only matches a type without whitespace. For types such as `array<string, mixed>` or `array{name: string, age: int}`
the pattern does not match and the parameter gets no `description` in the tool schema.

### How to reproduce

`symfony/ai-platform` v1.0.0-BETA1 (`MethodDescriber` is unchanged on `main`), PHP 8.5.

```php
use Symfony\AI\Platform\Contract\JsonSchema\Factory;

final class Demo
{
    /**
     * @param list<string>          $tags   A list of tags
     * @param array<string, string> $labels Map of field name to label
     */
    public function __invoke(array $tags, array $labels): string
    {
        return 'ok';
    }
}

echo json_encode((new Factory())->buildParameters(Demo::class, '__invoke'), \JSON_PRETTY_PRINT);
```

### Actual

`tags` has `"description": "A list of tags"`; `labels` has no `description`.

### Expected

Both parameters carry their description.
````

## 12. Claude Code / Codex bridges send only the last user message

**Verdict: ALREADY REPORTED.** Target: symfony/ai. Do not file.

- Code (installed BETA1): `harness/vendor/symfony/ai-claude-code-platform/Contract/MessageBagNormalizer.php` lines 43-62
  walk the messages in reverse and keep the first user text; `harness/vendor/symfony/ai-codex-platform/Contract/MessageBagNormalizer.php`
  is the same code. Read, not run.
- Existing issue: https://github.com/symfony/ai/issues/2544 "[Platform] ClaudeCode and Codex bridges drop conversation
  history; support multi-turn via CLI session resume" (open, 2026-09-16). Related RFC:
  https://github.com/symfony/ai/issues/2545.
- Changed since BETA1, but not resolved: https://github.com/symfony/ai/pull/2891 (merged 2026-11-06) makes both normalizers
  on `main` keep every user message and text block **after the last assistant message**. Earlier turns (the first request
  and the assistant's answer) are still dropped, so the two-step example of the draft still loses its first half.
- Cadenza workaround still present: `harness/src/Connection/Subscriptions.php`, `flattened()` (line 112).

## 13. Codex bridge: the bundled model list rejects the CLI's current models

**Verdict: FILE.** Target: symfony/ai (Platform, Codex bridge).

- Code: `harness/vendor/symfony/ai-codex-platform/ModelCatalog.php` lines 38-61: a fixed list (`gpt-5.4`, `gpt-5.4-mini`,
  `gpt-5.3-codex`, `gpt-5.3-codex-spark`, `gpt-5.2`) plus `$additionalModels`. Identical on `main`.
  `harness/vendor/symfony/ai-codex-platform/ModelClient.php` lines 57-58 always pass a model name to the CLI.
- **Reproduced** the catalog part: `(new ModelCatalog())->getModel('gpt-5.5')` throws
  `ModelNotFoundException: Model "gpt-5.5" not found in "Symfony\AI\Platform\Bridge\Codex\ModelCatalog"`.
- Local check: Codex CLI 0.167.2 on this machine, `~/.codex/models_cache.json` (fetched 2026-11-18) lists ten models; **none**
  of the five catalog names is among them, and the model configured in `~/.codex/config.toml` is unknown to the catalog.
- **Not verified:** the CLI's answer quoted in the draft ("The 'gpt-5.4' model is not supported when using Codex with a
  ChatGPT account"). Codex was not run for this check. The issue below therefore does not quote it; add it only after
  re-running `codex exec --model gpt-5.4 "hi"` and copying the real output.
- Existing reports: none specific to Codex (`Codex ModelCatalog`, `Codex model list`, `Codex catalog`). The general
  discussion https://github.com/symfony/ai/issues/1692 "[Platform] Shipping model catalogs is not a good idea" (open) is
  related and should be linked. The catalog was last aligned in https://github.com/symfony/ai/pull/1982.
- Cadenza workaround still present: `harness/src/Connection/Subscriptions.php` (reads `models_cache.json`, line 85, and
  passes the names as `additionalModels`).

### Ready-to-paste issue

**Title:** [Platform][Codex] Bundled `ModelCatalog` rejects the models the installed Codex CLI offers

**Body:**

````markdown
### Description

`Bridge\Codex\ModelCatalog` ships a fixed list: `gpt-5.4`, `gpt-5.4-mini`, `gpt-5.3-codex`, `gpt-5.3-codex-spark`,
`gpt-5.2`. The model router resolves the name against this catalog before the CLI is started, so any other name is
refused with `ModelNotFoundException`, even though the CLI is the one that knows which models the signed-in account can
use. With a current Codex CLI (0.167.2) none of the five names is in the CLI's own model list
(`~/.codex/models_cache.json`), and the model set in `~/.codex/config.toml` is unknown to the catalog.

This is a concrete case of #1692.

### How to reproduce

`symfony/ai-codex-platform` v1.0.0-BETA1 (the catalog is identical on `main`), Codex CLI 0.167.2, PHP 8.5.

```php
use Symfony\AI\Platform\Bridge\Codex\ModelCatalog;

// any model name the CLI lists that is not one of the five above, e.g. the "model" of ~/.codex/config.toml
(new ModelCatalog())->getModel('gpt-5.5');
```

### Actual

```
Symfony\AI\Platform\Exception\ModelNotFoundException:
Model "gpt-5.5" not found in "Symfony\AI\Platform\Bridge\Codex\ModelCatalog".
```

The only way to use such a model is to pass a `ModelCatalog` with `$additionalModels` and keep it in sync by hand.

### Expected

The bridge does not need a release to follow the CLI's model list: for example it accepts any model name and lets the
CLI validate it, or reads the CLI's model cache, or allows omitting the model so that the CLI default is used.
````
