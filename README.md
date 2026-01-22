# Cortex

Micro-framework PHP pour Symfony - La "Fondation" de NyxCorp.

## Structure

```
Cortex/
├── assets/                     # Assets JS/CSS (via Vite @cortex alias)
│   ├── js/
│   │   ├── utils.js            # Utilitaires généraux
│   │   ├── index.js            # Point d'entrée exports
│   │   └── components/
│   │       ├── tab_namespace.js
│   │       └── pop_fade_spinner.js
│   └── css/
│       └── theme.css           # Styles de base
│
└── src/
    ├── Bridge/
    │   ├── Doctrine/           # Adapters Doctrine/DBAL
    │   └── Symfony/
    │       ├── Bundle/         # CortexBridgeBundle
    │       │   ├── Resources/
    │       │   │   ├── views/theme/    # Templates Twig @CortexBridge
    │       │   │   ├── config/
    │       │   │   └── maker/          # Templates pour les makers
    │       │   ├── Maker/              # Commandes make:cortex:*
    │       │   └── DependencyInjection/
    │       ├── Controller/
    │       ├── Form/
    │       └── Twig/
    ├── Component/
    │   ├── Action/             # ActionHandler pattern
    │   ├── Collection/         # Collections utilitaires
    │   ├── Mapper/             # Data mappers
    │   ├── Middleware/         # Middleware chain
    │   └── Model/              # Model factories, stores
    └── ValueObject/            # Value objects (Email, HashedPassword, etc.)
```

## Usage

### Templates Twig

```twig
{% extends '@CortexBridge/theme/admin.html.twig' %}

{% include '@CortexBridge/theme/components/pager.html.twig' %}
```

### Assets JS

```js
// Dans assets/src/app.js
import { TabNamespace, PopFadeSpinner } from '@cortex/js';
import '@cortex/css/theme.css';
```

### PHP

```php
use Cortex\Component\Action\ActionHandler;
use Cortex\ValueObject\Email;
```

## Subtree

Cortex est géré via git subtree. Commandes disponibles :

```bash
make cortex-push   # Push les modifications vers le repo Cortex
make cortex-pull   # Pull les mises à jour depuis le repo Cortex
```
