# Resource Routing

Le `ControllerRouteLoader` dérive les routes depuis le nom de classe des controllers. Convention over configuration.

## Configuration

```yaml
# config/routes/modules/studio.yaml
actions:
    resource: studio
    type: cortex
```

```yaml
# config/routes/application.yaml
studio:
    resource: ./modules/studio.yaml
    name_prefix: studio/
    prefix: /studio
    defaults:
        _format: html
    options:
        module: studio
```

## Convention de nommage

| Classe | Path | Methods | Name |
|---|---|---|---|
| `{Model}ListAction` | `/{models}` | GET | `{model}/index` |
| `{Model}CreateAction` | `/{model}/create` | GET, POST | `{model}/create` |
| `{Model}UpdateAction` | `/{model}/{uuid}/edit` | GET, POST | `{model}/edit` |
| `{Model}EditAction` | `/{model}/{uuid}/edit` | GET, POST | `{model}/edit` |
| `{Model}ArchiveAction` | `/{model}/{uuid}/archive` | GET | `{model}/archive` |
| `{Model}{Custom}Action` | `/{model}/{uuid}/{custom}` | GET, POST | `{model}/{custom}` |

Le prefix (`/studio`) vient de `application.yaml`. Le `name_prefix` (`studio/`) est ajouté par Symfony.

### Pluralisation

Le suffixe `s` est ajouté pour les listes. Pour les modèles multi-mots, `toKebab()` convertit `ProductCategory` en `product-category`.

### Actions connues

Les actions `List`, `Create`, `Update`, `Edit`, `Archive` sont reconnues comme suffixes. Cela supporte les noms de modèles multi-mots :

- `ProductCategoryListAction` → model `ProductCategory`, action `List`
- `ProductCategoryCreateAction` → model `ProductCategory`, action `Create`

Les actions inconnues utilisent le fallback regex (premier mot PascalCase = model).

## Override avec `#[Route]`

`#[Route]` prend priorité sur la convention. Utile pour :

- Paramètres custom (`{fastmagRef}` au lieu de `{uuid}`)
- Requirements (`'^[A-Z0-9]{5,6}$'`)
- Options (`query_filters: true`)
- Routes multiples sur un même controller (archive + restore)
- Paths non-conventionnels

```php
// Convention ignorée — le #[Route] est utilisé
#[Route(
    path: '/shooting/{uuid}/process/{fastmagRef}',
    name: 'shooting/process',
    methods: ['GET', 'POST'],
    requirements: ['fastmagRef' => '^([A-Z0-9]{5,6})?$'],
)]
class ShootingProcessAction implements ControllerInterface { ... }
```

## Subpath

Les controllers dans des sous-dossiers de `Controller/Action/` reçoivent un prefix de nom automatique :

```
Application/Catalog/Controller/Action/Admin/ProductListAction.php
→ name: admin/product/index (+ name_prefix du module)
```

Le `matchesResource()` matche via deux patterns :
- `Application\{Resource}\Controller\Action` (module)
- `Controller\Action\{Resource}\` (subpath)

## Exemple : Studio (La Canadienne)

Controllers sans `#[Route]` (convention) :
- `ShootingListAction` → `GET /studio/shootings` (name: `studio/shooting/index`)
- `ShootingCreateAction` → `GET|POST /studio/shooting/create`
- `ShootingUpdateAction` → `GET|POST /studio/shooting/{uuid}/edit`
- `ShootingArticlesAction` → `GET|POST /studio/shooting/{uuid}/articles`

Controllers avec `#[Route]` (override) :
- `ShootingArchiveAction` → deux routes (archive + restore) avec `query_filters` option
- `ShootingProcessAction` → path custom avec `{fastmagRef}` requirement
- `AssetDisplayAction` → path custom `/asset/{url}`

## Multi-resource

Le loader supporte des chargements multiples (un par module). Chaque module a son propre `type: cortex` dans son fichier de routes.
