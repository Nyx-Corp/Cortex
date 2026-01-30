# Action Events

Le système d'événements Cortex permet d'intercepter et de modifier les réponses des Actions métier. Il utilise PSR-14 (`EventDispatcherInterface`) et s'intègre nativement avec Symfony.

## Architecture

```
ModelEvent (Cortex - abstract, StoppableEventInterface)
    │
    └── {Model}Event (Domain/{Domain}/Event - abstract)
            │
            ├── Domain\{Domain}\Action\{Model}Edit\Event
            ├── Domain\{Domain}\Action\{Model}Archive\Event
            └── Domain\{Domain}\Action\{Model}{Action}\Event
```

## Composants

### ModelEvent

Classe de base abstraite pour tous les événements domain. Implémente `StoppableEventInterface` de PSR-14.

```php
namespace Cortex\Component\Event;

abstract class ModelEvent implements StoppableEventInterface
{
    protected object $response;

    public function __construct(object $response)
    {
        $this->response = $response;
    }

    public function getResponse(): object
    {
        return $this->response;
    }

    public function setResponse(object $response): void
    {
        $this->response = $response;
    }

    public function stopPropagation(): void;
    public function isPropagationStopped(): bool;
}
```

### EventDispatcherAwareInterface

Interface pour les handlers qui émettent des événements.

```php
namespace Cortex\Component\Event;

use Psr\EventDispatcher\EventDispatcherInterface;

interface EventDispatcherAwareInterface
{
    public function setEventDispatcher(EventDispatcherInterface $dispatcher): void;
}
```

### EmitsActionEvents

Trait qui fournit la méthode `emit()` aux handlers.

```php
namespace Cortex\Component\Event;

trait EmitsActionEvents
{
    private ?EventDispatcherInterface $eventDispatcher = null;

    public function setEventDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->eventDispatcher = $dispatcher;
    }

    protected function emit(object $event): object
    {
        return $this->eventDispatcher?->dispatch($event) ?? $event;
    }
}
```

## Usage

### Dans un Handler

```php
namespace Domain\Club\Action\ClubEdit;

use Cortex\Component\Action\ActionHandler;
use Cortex\Component\Event\EmitsActionEvents;
use Cortex\Component\Event\EventDispatcherAwareInterface;

class Handler implements ActionHandler, EventDispatcherAwareInterface
{
    use EmitsActionEvents;

    public function __construct(
        private readonly ClubFactory $factory,
        private readonly ClubStore $store,
    ) {
    }

    public function __invoke(Command $command): Response
    {
        $model = $this->factory->create()
            ->with(...get_object_vars($command))
            ->build();

        $this->store->sync($model);

        // Émettre l'événement avec la réponse
        $this->emit($event = new Event(new Response($model)));

        // Retourner la réponse (potentiellement modifiée par les listeners)
        return $event->getResponse();
    }
}
```

### Classe Event de l'action

Chaque action a sa propre classe Event qui étend le ModelEvent du domain :

```php
namespace Domain\Club\Action\ClubEdit;

use Domain\Club\Event\ClubEvent;

class Event extends ClubEvent
{
    public function getResponse(): Response
    {
        return $this->response;
    }
}
```

### ModelEvent du domain

Chaque domain a un événement de base abstrait :

```php
namespace Domain\Club\Event;

use Cortex\Component\Event\ModelEvent;

abstract class ClubEvent extends ModelEvent
{
}
```

## Écouter les événements

### Listener pour une action spécifique

```php
use Domain\Club\Action\ClubEdit\Event;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: Event::class)]
class OnClubEdit
{
    public function __invoke(Event $event): void
    {
        $response = $event->getResponse();
        $club = $response->model;

        // Logique métier...

        // Modifier la réponse si nécessaire
        // $event->setResponse($newResponse);
    }
}
```

### Listener pour plusieurs événements (Subscriber)

```php
use Domain\Club\Action\ClubEdit\Event as ClubEditEvent;
use Domain\Club\Action\ClubArchive\Event as ClubArchiveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ClubEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ClubEditEvent::class => 'onClubChange',
            ClubArchiveEvent::class => 'onClubChange',
        ];
    }

    public function onClubChange(ClubEvent $event): void
    {
        // Traitement commun à tous les événements Club
    }
}
```

## Cas d'usage

### Enrichir la réponse

```php
#[AsEventListener(event: ClubEdit\Event::class)]
class AddFlashMessage
{
    public function __invoke(Event $event): void
    {
        $response = $event->getResponse();

        // Ajouter un message flash, des métadonnées, etc.
        $event->setResponse($response->withMessage('Club sauvegardé'));
    }
}
```

### Logging / Audit

```php
#[AsEventListener(event: ClubEdit\Event::class, priority: -100)]
class AuditLogger
{
    public function __invoke(Event $event): void
    {
        $this->logger->info('Club edited', [
            'club_uuid' => $event->getResponse()->model->uuid,
        ]);
    }
}
```

### Dispatch async vers message queue

```php
#[AsEventListener(event: ClubEdit\Event::class, priority: -100)]
class DispatchToMessenger
{
    public function __invoke(Event $event): void
    {
        $this->bus->dispatch(new ClubEditedMessage(
            $event->getResponse()->model->uuid
        ));
    }
}
```

### Webhook vers outils externes (n8n, etc.)

```php
#[AsEventListener(event: ClubEdit\Event::class, priority: -100)]
class NotifyWebhook
{
    public function __invoke(Event $event): void
    {
        $this->httpClient->request('POST', 'https://n8n.../webhook/...', [
            'json' => [
                'event' => 'club.edited',
                'club_uuid' => $event->getResponse()->model->uuid,
            ],
        ]);
    }
}
```

## Injection automatique

Le `ActionHandlerCompilerPass` détecte automatiquement les handlers qui implémentent `EventDispatcherAwareInterface` et injecte le dispatcher via le setter.

```php
// Dans ActionHandlerCompilerPass
if (is_subclass_of($class, EventDispatcherAwareInterface::class)) {
    $definition->addMethodCall(
        'setEventDispatcher',
        [new Reference(EventDispatcherInterface::class)]
    );
}
```

## Génération avec les Makers

Les makers génèrent automatiquement les classes Event et le code d'émission :

```bash
# Crée le CRUD complet avec événements
./bin/php bin/console make:cortex:crud Admin Club Club

# Crée une action spécifique avec événement
./bin/php bin/console make:cortex:action Admin Club Club Publish
```

Fichiers générés pour une action :
```
Domain/Club/
├── Event/
│   └── ClubEvent.php           # Événement de base du modèle
└── Action/ClubEdit/
    ├── Command.php
    ├── Handler.php             # Avec emit() intégré
    ├── Response.php
    └── Event.php               # Événement de l'action
```

## Bonnes pratiques

1. **Priorités** : Utilisez des priorités positives pour les listeners qui modifient la réponse, négatives pour les side-effects (logs, webhooks).

2. **Idempotence** : Les listeners de side-effects doivent être idempotents (re-exécutables sans effet secondaire).

3. **Performance** : Pour les traitements longs, dispatchez vers Messenger plutôt que de bloquer la réponse.

4. **Graceful degradation** : Si le dispatcher n'est pas injecté, `emit()` retourne simplement l'événement sans erreur.
