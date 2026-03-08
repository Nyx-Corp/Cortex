# Actions — Domain Command Pattern

## Action Structure

Each domain action follows the Command/Handler/Response pattern:

```
Domain/{Domain}/Action/{Model}{Action}/
├── Command.php      # Data transfer object (constructor = schema)
├── Handler.php      # Business logic (implements ActionHandler)
├── Response.php     # Result wrapper
├── Event.php        # Optional event (extends ModelEvent)
└── Exception.php    # Domain-specific exception
```

## Create vs Update vs Archive

### Create

```php
// Command — no uuid
class Command
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {}
}

// Handler — factory->create()->build()
public function __invoke(Command $command): Response
{
    $model = $this->factory->create()
        ->with(...get_object_vars($command))
        ->build();

    $this->store->sync($model);
    return new Response($model);
}
```

### Update

```php
// Command — uuid required
class Command
{
    public function __construct(
        public readonly Uuid $uuid,
        public readonly string $name,
        public readonly string $email,
    ) {}
}

// Handler — same pattern, uuid ensures update
public function __invoke(Command $command): Response
{
    $model = $this->factory->create()
        ->with(...get_object_vars($command))
        ->build();

    $this->store->sync($model);
    return new Response($model);
}
```

### Archive

```php
// Command — model instance + flag
class Command
{
    public function __construct(
        public readonly Model $model,
        public readonly bool $isArchived = true,
    ) {}
}
```

## `#[Action]` Attribute

Declares the command class for a FormType, replacing `configureOptions()`:

```php
use Cortex\Bridge\Symfony\Form\Attribute\Action;

#[Action(MannequinCreate\Command::class)]
class MannequinCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('firstname')->add('lastname');
    }
}
```

The `CommandMapperExtension` resolves the command class from:
1. `#[Action]` attribute (preferred)
2. `command_class` option in `configureOptions()` (legacy fallback)

## CommandFormType (Generic Fallback)

Actions without a dedicated FormType get an auto-generated form based on the Command constructor. The `ActionHandlerCompilerPass` pre-calculates field configurations at container build time (no runtime reflection).

This enables CLI, API, and MCP access to any action without writing a FormType.

## Events

Handlers can emit events via `EmitsActionEvents` trait:

```php
class Handler implements ActionHandler, EventDispatcherAwareInterface
{
    use EmitsActionEvents;

    public function __invoke(Command $command): Response
    {
        // ... business logic ...

        $this->emit($event = new Event(new Response($model)));
        return $event->getResponse();
    }
}
```

Events extend `ModelEvent` and can be listened to by subscribers (e.g., `AlertEventSubscriber` for automatic flash messages).
