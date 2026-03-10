# UPGRADE from 1.x to 2.0

## Breaking Changes

### `Edit` → `Create` + `Update`

The unified `{Model}Edit` action pattern has been split into `{Model}Create` and `{Model}Update`.

**Before (v1):**
```
Domain/{Domain}/Action/{Model}Edit/
├── Command.php    # ?Uuid $uuid = null (nullable = create or update)
├── Handler.php
├── Response.php
└── Exception.php

Application/{Module}/Controller/Action/{Model}EditAction.php   # handles both routes
Application/{Module}/Form/{Model}EditType.php                  # single form type
```

**After (v2):**
```
Domain/{Domain}/Action/{Model}Create/
├── Command.php    # no uuid (creation only)
├── Handler.php
├── Response.php
└── Exception.php

Domain/{Domain}/Action/{Model}Update/
├── Command.php    # Uuid $uuid required
├── Handler.php
├── Response.php
└── Exception.php

Application/{Module}/Controller/Action/{Model}CreateAction.php  # POST /{model}/create
Application/{Module}/Controller/Action/{Model}UpdateAction.php  # POST /{model}/{uuid}/edit
Application/{Module}/Form/{Model}CreateType.php
Application/{Module}/Form/{Model}UpdateType.php
```

**Migration steps:**
1. Split `Command.php`: move uuid to `Update\Command` as required, remove from `Create\Command`
2. Split `Handler.php`: Create handler doesn't load existing, Update handler does
3. Duplicate `Response.php` and `Exception.php` for both namespaces
4. Split controller: `CreateAction` (no model injection, redirects after success), `UpdateAction` (model injection required)
5. Split form type: both can share the same fields, or differ as needed
6. Routes remain the same (`{model}/create` and `{model}/{uuid}/edit`)

### `#[Action]` Attribute

FormTypes can now declare their command class via the `#[Action]` attribute instead of `configureOptions()`.

**Before (v1):**
```php
class MannequinEditType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['command_class' => Command::class]);
    }
}
```

**After (v2):**
```php
#[Action(Command::class)]
class MannequinCreateType extends AbstractType
{
    // No need for configureOptions() with command_class
}
```

The `command_class` option in `configureOptions()` still works as a legacy fallback.

## New Features

### CommandFormType (Fallback)

Actions without a dedicated FormType automatically get a generic `CommandFormType` that builds form fields from the Command constructor parameters. This enables CLI, API, and MCP triggers without writing a FormType.

Type mapping:
| PHP Type | Form Type |
|---|---|
| `string` | `TextType` |
| `int` | `IntegerType` |
| `float` | `NumberType` |
| `bool` | `CheckboxType` |
| `BackedEnum` | `EnumType` |
| `DateTimeInterface` | `DateTimeType` |
| Known Model | `TextType` + `ModelTransformer` |

### CLI Trigger

All domain actions are auto-registered as Symfony console commands:

```bash
bin/console studio:mannequin:create --firstname=Julie --gender=female
bin/console account:account:update --uuid=... --firstName=New --format=json
```

### REST API Trigger

Routes are auto-generated from action metadata:

```
POST   /api/{domain}/{model}/{action}       # Create, custom actions
PUT    /api/{domain}/{model}/{uuid}          # Update
DELETE /api/{domain}/{model}/{uuid}/archive  # Archive
```

Enable by adding to your routes config:
```yaml
cortex_api:
    resource: .
    type: cortex_api
```

Auth must be configured separately (firewall on `/api/`).

### MCP Trigger

`ActionToolProvider` exposes all domain actions as MCP tools. Integrate with your MCP server implementation.

### AlertEventSubscriber

Handlers using `EmitsActionEvents` trait will automatically generate flash messages when a session is active. No-op for CLI/API contexts.
