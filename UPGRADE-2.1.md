# UPGRADE 2.0 → 2.1

## Resource Routing (Convention over Configuration)

Les routes CRUD sont désormais dérivées du nom de classe. `#[Route]` est optionnel (override).

### Convention

| Classe | Path | Methods | Name |
|---|---|---|---|
| `{Model}ListAction` | `/{models}` | GET | `{model}/index` |
| `{Model}CreateAction` | `/{model}/create` | GET, POST | `{model}/create` |
| `{Model}UpdateAction` | `/{model}/{uuid}/edit` | GET, POST | `{model}/edit` |
| `{Model}EditAction` | `/{model}/{uuid}/edit` | GET, POST | `{model}/edit` |
| `{Model}ArchiveAction` | `/{model}/{uuid}/archive` | GET | `{model}/archive` |
| `{Model}{Custom}Action` | `/{model}/{uuid}/{custom}` | GET, POST | `{model}/{custom}` |

### Avant (v2.0)

```php
#[Route(path: '/shootings', name: 'shooting/index', methods: ['GET'])]
class ShootingListAction implements ControllerInterface { ... }
```

### Après (v2.1)

```php
// Aucun attribut — path, name et methods sont dérivés par convention
class ShootingListAction implements ControllerInterface { ... }
```

Pour les cas non-conventionnels (paramètres custom, requirements, options), `#[Route]` reste possible et prend priorité sur la convention.

### Migration routes config

```yaml
# AVANT (type: attribute — scanne les fichiers PHP pour #[Route])
actions:
    resource: ../../../src/Application/Studio/Controller/Action/
    type: attribute

# APRÈS (type: cortex — convention over configuration)
actions:
    resource: studio
    type: cortex
```

Le `resource` passe du chemin filesystem au nom du module. Le prefix et name_prefix restent dans `application.yaml`.

### Multi-resource

Chaque module peut avoir son propre `type: cortex` import. Le loader supporte les chargements multiples (un par module).

---

## Subpath — variantes applicatives

Quand un module sert plusieurs applications (admin + front + api), organiser les controllers en sous-dossiers :

```
Application/Catalog/Controller/Action/
    Admin/ProductListAction.php      # Controllers admin
    Front/ProductShowAction.php      # Controllers front
    ProductSyncAction.php            # pas spécifique → racine
```

Le `ControllerRouteLoader` matche automatiquement les subpaths via le pattern `Controller\Action\{Subpath}\`. Les routes dans un subpath reçoivent un prefix de nom automatique (ex: `admin/product/index`).

Le CrudMaker supporte `--output-subpath=admin` :

```bash
php bin/console make:cortex:crud Catalog Catalog Product --output-subpath=admin
```

Les fichiers générés sont placés dans `Controller/Action/Admin/` avec le bon namespace PSR-4.

---

## Templates JSON — API via Twig

Le même controller sert HTML et JSON. Le `ControllerSubscriber` résout le template par format.

### Liste

```twig
{# templates/studio/shooting/index.json.twig #}
{% extends '@_theme/layout/list.json.twig' %}

{% block item_fields %}
"uuid": "{{ item.uuid }}",
"name": "{{ item.name }}",
"date": "{{ item.date|date('c') }}",
"_links": {
    "self": "{{ path('studio/shooting/edit', {uuid: item.uuid}) }}"
}
{% endblock %}
```

`list.json.twig` fournit automatiquement : `data` (items), `meta` (pagination), `_links` (navigation), `filters` (filtres disponibles).

### Réponse write

```twig
{# templates/studio/shooting/archive.json.twig #}
{% extends '@_theme/layout/response.json.twig' %}

{% block response_fields %}
"uuid": "{{ response.shooting.uuid }}",
"archived": true
{% endblock %}
```

### Content-Type

Les templates non-HTML retournent automatiquement le bon `Content-Type` (`application/json`, `text/csv`, etc.).

Sans `.json.twig`, le fallback est un `JsonResponse` brut du controller result.

---

## MCP — Schemas depuis le Form System

Les outils MCP utilisent les FormTypes + traductions pour leurs schemas.

### Descriptions

- Champs : `help` ou `label` de la FormView, traduit via le `translation_domain` du formulaire
- Outil : clé de trad `{model}.form.description` dans le domaine du module
- Types : dérivés des `block_prefixes` de la FormView
- Choices : depuis les `choices` de la FormView (pour EnumType, ChoiceType)

### Traductions

```yaml
# translations/studio+intl-icu.fr.yaml
mannequin:
    form:
        description: "Gestion des profils mannequin pour les shootings photo studio."
    fields:
        firstname:
            label: Prénom
            help: "Prénom du mannequin"
```

Aucun attribut nécessaire — tout est dans les traductions existantes.

---

## Breaking Changes

### `ControllerRouteLoader::$loaded`

Changé de `bool` à `array $loadedResources`. Si vous sous-classez le loader (improbable), adaptez votre code.

### `ActionToolProvider::__construct`

Nouveau paramètre `TranslatorInterface $translator` (autowired). Si vous instanciez manuellement, ajoutez le paramètre.

### `PathCollection::mirror()`

Nouveau paramètre optionnel `?\Closure $pathTransformer`. Backward-compatible.

### Import `Annotation\Route`

Le `ControllerRouteLoader` supporte les deux via `IS_INSTANCEOF`, mais migrez vers `Attribute\Route` :

```diff
- use Symfony\Component\Routing\Annotation\Route;
+ use Symfony\Component\Routing\Attribute\Route;
```
