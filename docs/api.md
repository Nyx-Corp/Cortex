# API Route Generation

Cortex auto-generates REST API endpoints from domain actions. No manual route definitions needed.

## How it works

`ApiRouteLoader` scans all domain action FormTypes with `#[Action]` attributes, then generates one REST route per action per active API version.

Routes are loaded via `config/routes.yaml`:

```yaml
cortex_api:
    resource: .
    type: cortex_api
```

The loader reads `actionMetadata` (collected from `#[Action]` and `#[Api]` attributes) and builds routes following REST conventions:

| Action | HTTP Method | Path pattern |
|--------|------------|--------------|
| `create` | POST | `{prefix}/{domain}/{model}` |
| `update` | PUT, PATCH | `{prefix}/{domain}/{model}/{uuid}` |
| `archive` | DELETE | `{prefix}/{domain}/{model}/{uuid}` |
| Custom | POST | `{prefix}/{domain}/{model}/{uuid}/{action}` |

## Path prefix configuration

The `$pathPrefix` constructor argument controls URL structure:

| Config | Prefix | Example route |
|--------|--------|---------------|
| `null` (default) | `/api/v{version}` | `POST /api/v1/club/member` |
| `'/p'` | `/p` | `POST /p/club/member` |

When a custom prefix is set, version segments are omitted from paths. Route names also change:

- Default: `cortex_api_v1_club_member_create`
- Custom prefix: `cortex_api_club_member_create`

Set the prefix via a container parameter bound to `ApiRouteLoader::$pathPrefix`.

## Rate limit fallback

`ApiRateLimitWarningSubscriber` is a safety net for projects that expose API/MCP endpoints without installing a rate limiter. It listens on `kernel.request` (priority 100) and logs a warning for any request matching the API prefix or `/_mcp`.

When Gandalf is installed, its `RateLimitSubscriber` replaces this fallback via the `cortex.api.rate_limit_guard` service tag (compiler pass substitution).

The fallback also accepts the `$pathPrefix` argument to match custom prefixes.

## Versioning and deprecation

See [api-versioning.md](api-versioning.md) for version lifecycle (`since`, `deprecated`, `sunset`), version transformers, and OpenAPI docs generation.

## Security

Cortex generates routes but provides **no authentication or authorization**. Projects must secure API endpoints via:

- A Symfony firewall with a stateless authenticator (e.g. Bearer tokens)
- `access_control` rules matching the chosen path prefix

For a ready-made security layer, see [Gandalf security documentation](../../../Gandalf/docs/security.md).
