# Cortex

[![CI](https://github.com/Nyx-Corp/Cortex/actions/workflows/ci.yml/badge.svg)](https://github.com/Nyx-Corp/Cortex/actions/workflows/ci.yml)

Micro-framework DDD (Domain-Driven Design) pour Symfony. La "Fondation" de NyxCorp.

**Stack** : PHP 8.4, Symfony 7.4, Doctrine DBAL 3

## Architecture

```
Cortex/
├── src/
│   ├── Component/                  # Composants purs PHP (zero framework)
│   │   ├── Action/                 # ActionHandler pattern (Command/Handler/Event)
│   │   ├── Collection/             # AsyncCollection, StructuredMap
│   │   ├── Mapper/                 # ArrayMapper, CallbackMapper, RelationMapper
│   │   ├── Middleware/             # MiddlewareChain orchestration
│   │   └── Model/                  # Factory, Store, Query, Pager
│   │
│   ├── Bridge/
│   │   ├── Doctrine/               # DbalAdapter, JoinDefinition, Preloader
│   │   └── Symfony/
│   │       ├── Bundle/             # CortexBridgeBundle
│   │       │   ├── Maker/          # make:cortex:model, crud, action
│   │       │   ├── Resources/
│   │       │   │   ├── views/      # Templates Twig (@CortexBridge)
│   │       │   │   ├── config/     # Service definitions
│   │       │   │   ├── maker/      # Code generation templates
│   │       │   │   └── translations/  # i18n (fr, en)
│   │       │   └── DependencyInjection/
│   │       ├── Controller/         # ControllerInterface, ControllerSubscriber
│   │       ├── Form/               # ModelQueryType, DataTransformers
│   │       ├── Module/             # ModuleLoader
│   │       ├── Translation/        # ModuleTranslator
│   │       └── Twig/               # Extensions, filters
│   │
│   └── ValueObject/                # Email, HashedPassword, PositiveInt, Uuid
│
├── assets/
│   ├── js/
│   │   ├── admin.js                # Admin entry point
│   │   ├── index.js                # Public exports
│   │   ├── utils.js                # DOM utilities
│   │   ├── controllers/            # Stimulus controllers
│   │   │   ├── alerts/             # Toast notifications (store + triggers)
│   │   │   ├── search-filters/     # Filtres de recherche
│   │   │   ├── popover/            # Popovers
│   │   │   ├── form-dirty/         # Détection formulaire modifié
│   │   │   └── theme-toggle/       # Dark/light mode
│   │   └── components/
│   │       ├── tab_namespace.js    # Tabs avec namespace
│   │       └── pop_fade_spinner.js # Loading spinner
│   └── css/
│       ├── theme.css               # Design tokens + base styles
│       ├── theme-dark.css          # Dark mode overrides
│       └── admin.css               # Admin layout
│
├── tests/
│   └── Unit/                       # 16 tests unitaires
│
├── docs/                           # Documentation technique
└── .github/workflows/ci.yml       # CI (PHPStan, CS-Fixer, Deptrac, PHPUnit)
```

## Composants

### Component (pur PHP)

| Module | Description | Doc |
|--------|-------------|-----|
| **Action** | Pattern Command/Handler avec événements PSR-14 | [events.md](docs/events.md) |
| **Collection** | Collections lazy (AsyncCollection) et maps structurées | [async-collection.md](docs/async-collection.md) |
| **Mapper** | Transformation bidirectionnelle DB ↔ Model | [array-mapper.md](docs/array-mapper.md) |
| **Middleware** | Chaîne de middlewares ordonnée par priorité | [index.md](docs/index.md) |
| **Model** | Factory, Store, Query, Pager — cycle de vie des modèles | [index.md](docs/index.md) |

### Bridge/Doctrine

Persistence DBAL avec JOINs automatiques et prévention N+1 via preloading.

[Documentation](docs/bridge-doctrine.md)

### Bridge/Symfony

Intégration Symfony complète : ValueResolver (injection automatique de collections), Forms, ControllerSubscriber (template auto-rendering), Twig extensions.

[Documentation](docs/bridge-symfony.md)

### ValueObject

Objets valeur réutilisables : `Email`, `HashedPassword`, `PositiveInt`, `RegisteredClass`, `SecurityToken`.

### Makers

Générateurs de code pour l'architecture DDD :

```bash
make:cortex:model {Domain} {Model}              # Model + Collection + Factory + Store
make:cortex:model {Domain} {Model} --dbal        # + Doctrine Mapper + Migration
make:cortex:crud {Module} {Domain} {Model}       # CRUD complet (controllers, forms, templates)
make:cortex:action {Module} {Domain} {Model} {Action}  # Action métier
```

[Documentation](docs/makers.md)

## Intégration

### Twig

```twig
{% extends '@CortexBridge/theme/admin.html.twig' %}
{% include '@CortexBridge/theme/components/pager.html.twig' %}
```

### JavaScript (via Vite)

```js
import { TabNamespace, PopFadeSpinner } from '@cortex/js';
import '@cortex/css/theme.css';

// Stimulus controllers
import AlertsController from '@cortex/js/controllers/alerts';
application.register('alerts', AlertsController);
```

### PHP

```php
use Cortex\Component\Action\ActionHandler;
use Cortex\Component\Model\Factory\ModelFactory;
use Cortex\ValueObject\Email;
```

## Développement

### Installation standalone

```bash
composer install
```

### QA

```bash
vendor/bin/phpunit                          # Tests unitaires
vendor/bin/phpstan analyse --no-progress    # Analyse statique (level 6)
vendor/bin/php-cs-fixer fix --dry-run       # Code style (@Symfony + PHP 8.4)
vendor/bin/deptrac --no-progress            # Architecture (layers DDD)
```

### Intégré dans un projet

Cortex est embarqué via `git subtree` dans les projets NyxCorp :

```bash
# Depuis le projet hôte
make cortex-push   # Push les modifications vers ce repo
make cortex-pull   # Pull les mises à jour depuis ce repo
make cortex-status # Affiche l'état du subtree
```

## Documentation

| Document | Description |
|----------|-------------|
| [Architecture](docs/index.md) | Vue d'ensemble, flux de données, concepts clés |
| [ArrayMapper](docs/array-mapper.md) | Transformation bidirectionnelle DB ↔ Model |
| [AsyncCollection](docs/async-collection.md) | Collections lazy avec context propagation |
| [Bridge/Doctrine](docs/bridge-doctrine.md) | Persistence DBAL, JOINs, preloading N+1 |
| [Bridge/Symfony](docs/bridge-symfony.md) | ValueResolver, Forms, Controllers, Twig |
| [Action Events](docs/events.md) | Système d'événements PSR-14 pour les Actions |
| [Makers](docs/makers.md) | Générateurs de code DDD |

## Licence

Proprietary — NyxCorp
