# API Versioning

Cortex provides automatic API versioning with deprecation lifecycle management. Versions are auto-derived from `#[Api]` attributes — zero configuration required.

## How it works

Every domain action automatically gets an API endpoint. By default, all actions are available from version 1. The `#[Api]` attribute on FormTypes controls version lifecycle:

```php
use Cortex\Bridge\Symfony\Form\Attribute\Action;
use Cortex\Bridge\Symfony\Form\Attribute\Api;

#[Action(AccountCreate\Command::class)]
#[Api(since: 1)]
class AccountCreateType extends AbstractType { }

#[Action(AccountUpdate\Command::class)]
#[Api(since: 2, deprecated: 3, sunset: '2026-09-01')]
class AccountUpdateType extends AbstractType { }
```

- `since`: The API version this action first appeared in
- `deprecated`: The version from which this action is marked deprecated
- `sunset`: ISO date after which the deprecated action may be removed

No `#[Api]` attribute = available in all versions (equivalent to `since: 1`).

## URL pattern

All API routes are prefixed with the version number:

```
POST   /api/v1/account/account          → Create
PUT    /api/v1/account/account/{uuid}   → Update
DELETE /api/v1/account/account/{uuid}   → Archive
POST   /api/v1/catalog/product/{uuid}/sync → Custom action
```

## Version derivation

Active versions are automatically derived from all `#[Api(since: N)]` values found across FormTypes. No `cortex.yaml` configuration needed.

Example: if your FormTypes declare `since: 1`, `since: 1`, `since: 2`, the active versions are `[1, 2]`. Routes are generated for each version, excluding actions not yet available in that version.

## Response headers

Every API response includes:

| Header | Description |
|--------|-------------|
| `X-API-Version` | The version used for this request |
| `Deprecation: true` | Present if the action is deprecated in the requested version |
| `Sunset: <RFC 7231 date>` | Present if a sunset date is defined |

## Version transformers

For backwards-compatible changes between versions, implement `VersionTransformerInterface`:

```php
use Cortex\Bridge\Symfony\Api\VersionTransformerInterface;

class AccountUpdateTransformer implements VersionTransformerInterface
{
    public function getCommandClass(): string
    {
        return AccountUpdate\Command::class;
    }

    public function transformRequest(array $data, int $fromVersion): array
    {
        // v1 used "name", v2 split into "firstName" + "lastName"
        if ($fromVersion < 2 && isset($data['name'])) {
            [$first, $last] = explode(' ', $data['name'], 2);
            $data['firstName'] = $first;
            $data['lastName'] = $last ?? '';
            unset($data['name']);
        }

        return $data;
    }

    public function transformResponse(mixed $data, int $toVersion): mixed
    {
        return $data;
    }
}
```

Transformers are auto-discovered via the `cortex.api.version_transformer` tag (autoconfigured).

## OpenAPI documentation

The docs page at `/docs/api` shows a version selector when multiple versions exist. Each version has its own OpenAPI spec:

- Dev: generated at runtime via `/docs/api/v{N}/openapi.yaml`
- Prod: pre-built static files via `cortex:api:dump`

```bash
# Dump all versions
bin/console cortex:api:dump

# Dump specific version
bin/console cortex:api:dump --api-version=1

# Custom output directory
bin/console cortex:api:dump --output=docs/api
```

## Security

Update your `security.yaml` access control patterns to include the version prefix:

```yaml
access_control:
    - { path: '^/api/(v\d+/)?account', roles: ROLE_ADMIN }
    - { path: ^/api/, roles: ROLE_USER }
```

The `(v\d+/)?` pattern matches both versioned and unversioned paths.
