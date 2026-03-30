# ModelRepresentation

`ModelRepresentation` definit comment un modele Domain apparait dans differents contextes :
persistence (DB), API, Messenger, export. C'est le systeme de normalisation de Cortex.

**Terminologie** : "Representation" — Vaughn Vernon, *Implementing Domain-Driven Design* (2013).

**Fichier source** : `src/Component/Mapper/ModelRepresentation.php`

## Table des matieres

1. [Vue d'ensemble](#vue-densemble)
2. [Interface](#interface)
3. [Groupes](#groupes)
4. [Syntaxe des groupes](#syntaxe-des-groupes)
5. [CortexNormalizer](#cortexnormalizer)
6. [Events](#events)
7. [Security — scopes et voters](#security)
8. [Exemples](#exemples)

---

## Vue d'ensemble

Chaque modele a une classe Representation qui declare :
- **Comment** transformer le modele (mappers par groupe)
- **Quels champs** exposer par contexte (groupes avec heritage et propagation)
- **Quels groupes** sont publics (pas de scope requis)

```
Domain Model
     │
     ▼
ModelRepresentation
     ├── writer('store')   → ArrayMapper pour persistence (snake_case, FK)
     ├── writer('default') → ArrayMapper pour API (camelCase)
     ├── reader('store')   → ArrayMapper pour hydratation DB
     ├── reader('default') → ArrayMapper pour input API
     ├── groups()          → champs par contexte (store, id, list, detail, full)
     └── publicGroups()    → groupes sans scope (default: id, list)
```

Le mapping vit dans l'infrastructure (ou `Component/*/Representation/` pour les libs),
jamais sur le modele Domain. Le Domain reste un POPO sans attributs framework.

---

## Interface

```php
interface ModelRepresentation
{
    public function writer(string $group = 'default'): ArrayMapper;
    public function reader(string $group = 'default'): ArrayMapper;
    public function groups(): array;
    public function publicGroups(): array;
}
```

Annotee `#[Model(Page::class)]` (meme attribut que Factory et Store).

### writer(group)

Mapper outbound : model → array. Chaque groupe peut avoir son propre mapper.
Convention : `match ($group)` avec fallback `default`.

### reader(group)

Mapper inbound : array → model data. Meme pattern.

### groups()

Listes de champs par contexte. Voir [Syntaxe des groupes](#syntaxe-des-groupes).

### publicGroups()

Groupes accessibles sans scope de securite. Utiliser `DefaultPublicGroupsTrait`
pour le default (`['id', 'list']`).

---

## Groupes

### Convention de nommage

| Groupe | Usage | Scope requis |
|--------|-------|--------------|
| `store` | Persistence DB (obligatoire si DbalMapper existe) | Non (interne) |
| `id` | Identifiant seul | Non (public) |
| `list` | Champs cles pour les listes | Non (public) |
| `detail` | Vue detaillee | Oui |
| `full` | Tous les champs | Oui |

### Le groupe `store`

Obligatoire si un DbalMapper existe pour le modele. Verifie par le `RepresentationRegistry`
au premier acces (lazy validation injectee par le CompilerPass).

---

## Syntaxe des groupes

Inspiree de [MajoraFramework/Normalizer](https://github.com/Nyxis/MajoraFrameworkExtraBundle/tree/master/src/Majora/Framework/Normalizer) (Nyxis, ~2015).

| Syntaxe | Nom | Effet |
|---------|-----|-------|
| `'field'` | Champ simple | Inclure le champ |
| `'field@group'` | Propagation | Inclure le champ, normaliser la relation avec ce groupe |
| `'@group'` | Heritage | Inclure tous les champs d'un autre groupe |
| `'?field'` | Optionnel | Omettre si la valeur est null |
| `'?field@group'` | Combo | Optionnel + propagation |

### Heritage (@group)

```php
'list'   => ['uuid', 'title', 'slug', 'status'],
'detail' => ['@list', 'body', 'metaTitle'],
// detail = uuid, title, slug, status, body, metaTitle
```

### Propagation (field@group)

```php
'list'   => ['uuid', 'title', 'author@id'],
// author sera normalise avec le groupe 'id' de sa propre Representation
// → { "uuid": "...", "title": "...", "author": { "uuid": "..." } }
```

### Override (dernier gagne)

```php
'detail' => ['@list', 'author@detail'],
// @list herite author@id, mais author@detail l'ecrase (dernier gagne)
```

### Collections

La propagation fonctionne sur les collections d'objets :

```php
'list' => ['uuid', 'title', 'tags@id'],
// tags = [{ "uuid": "t-1" }, { "uuid": "t-2" }]
```

---

## CortexNormalizer

Pont entre les Representations Cortex et l'ecosysteme Symfony Serializer.

**Fichier** : `src/Bridge/Symfony/Serializer/CortexNormalizer.php`

Implemente `NormalizerInterface` et `DenormalizerInterface`. Auto-tague
`serializer.normalizer` par Symfony.

### Usage

```php
// Via le Sf Serializer (injection standard)
$array = $serializer->normalize($article, context: [
    'cortex_group' => 'list',
]);

// Denormalize
$data = $serializer->denormalize($input, Article::class, context: [
    'cortex_group' => 'store',
]);
```

### RepresentationRegistry

Collecte automatique via `ModelProcessorCompilerPass`. Toutes les classes
implementant `ModelRepresentation` avec `#[Model]` sont enregistrees.

---

## Events

Deux events dispatches avant chaque normalisation/denormalisation.

### PreNormalizeEvent

```php
use Cortex\Bridge\Symfony\Serializer\Event\PreNormalizeEvent;

// Proprietes
$event->modelClass;       // FQCN du modele
$event->data;             // Instance du modele
$event->getGroup();       // Groupe demande
$event->setGroup('id');   // Modifier le groupe
$event->stopPropagation(); // Empecher les listeners suivants
```

### PreDenormalizeEvent

Meme interface, `$event->data` est un `array`.

### Implementer un listener

```php
#[AsEventListener(event: PreNormalizeEvent::class, priority: 100)]
class MyListener
{
    public function __invoke(PreNormalizeEvent $event): void
    {
        // Inspecter, modifier le groupe, ou stopper la propagation
    }
}
```

Les events implementent `StoppableEventInterface` (PSR-14). Le dispatcher
Symfony respecte `isPropagationStopped()` nativement.

---

## Security

Les groupes de representation sont securises via le `role_hierarchy` standard
de Symfony et des voters Gandalf.

### Scopes

Format : `model@group` (ex: `page@detail`, `article@full`).
Supporte les wildcards `fnmatch()` : `*@detail`, `page@*`.

### Configuration

```yaml
# config/packages/security.yaml
security:
    role_hierarchy:
        ROLE_USER:        ['*@detail']
        ROLE_EDITOR:      ['ROLE_USER', 'article@full', 'page@full']
        ROLE_ADMIN:       ['ROLE_EDITOR', '*@full']
        ROLE_SUPER_ADMIN: ['ROLE_ADMIN']
```

### Fonctionnement

1. Le `CortexNormalizer` dispatch `PreNormalizeEvent`
2. Le `RepresentationSecurityListener` (Gandalf, priorite 100) intercepte
3. Si le groupe est dans `publicGroups()` → autorise
4. Sinon, verifie via voter : le scope `model@group` est-il dans les roles resolus ?
5. Si refuse → downgrade vers le groupe autorise le plus riche, puis `stopPropagation()`

### Groupes publics

`publicGroups()` retourne les groupes accessibles sans scope.
Default via `DefaultPublicGroupsTrait` : `['id', 'list']`.

```php
// Override pour un modele sensible (ex: Account)
public function publicGroups(): array
{
    return ['id'];  // meme 'list' requiert un scope
}
```

---

## Exemples

### Representation complete

```php
#[Model(Page::class)]
class PageRepresentation implements ModelRepresentation
{
    use DefaultPublicGroupsTrait;

    public function writer(string $group = 'default'): ArrayMapper
    {
        return match ($group) {
            'store' => new ArrayMapper([
                'status' => PageStatus::class,
                'metaTitle' => 'meta_title',
                'archivedAt' => Value::Date,
            ]),
            default => new ArrayMapper(
                mapping: ['archivedAt' => Value::Date],
                format: Strategy::AutoMapCamel,
            ),
        };
    }

    public function reader(string $group = 'default'): ArrayMapper
    {
        return match ($group) {
            'store' => new ArrayMapper(
                mapping: [
                    'uuid' => fn(string $v) => new Uuid($v),
                    'status' => PageStatus::class,
                    'archivedAt' => Value::Date,
                ],
                format: Strategy::AutoMapCamel,
            ),
            default => new ArrayMapper(
                mapping: ['uuid' => fn(string $v) => new Uuid($v)],
                format: Strategy::AutoMapCamel,
            ),
        };
    }

    public function groups(): array
    {
        return [
            'store' => ['uuid', 'title', 'slug', 'status', 'body',
                         'metaTitle', 'metaDescription', 'position', 'archivedAt'],
            'id'     => ['uuid'],
            'list'   => ['uuid', 'title', 'slug', 'status', 'position', '?excerpt'],
            'detail' => ['@list', 'body', 'metaTitle', 'metaDescription'],
            'full'   => ['@detail', 'archivedAt'],
        ];
    }
}
```

### DbalMapper simplifie

```php
#[Middleware(Page::class, on: Scope::All, handler: 'onDbal', priority: 2)]
class DbalPageMapper implements ModelMiddleware
{
    use DbalModelAdapterTrait;

    public function __construct(DbalBridge $dbalBridge, PageRepresentation $representation)
    {
        $this->dbal = $dbalBridge->createAdapter(new DbalMappingConfiguration(
            table: 'synapse_page',
            modelClass: Page::class,
            modelToTableMapper: $representation->writer('store'),
            tableToModelMapper: $representation->reader('store'),
        ));
    }
}
```

### Normalisation avec groupe securise

```php
// Dans un controller
$data = $this->serializer->normalize($page, context: [
    'cortex_group' => 'full',
]);
// Si l'utilisateur n'a pas le scope page@full,
// le listener downgrade automatiquement vers 'detail' ou 'list'
```

### Propagation recursive

```php
// Article avec categorie et tags
'detail' => ['@list', 'body', 'category@list', 'tags@id'],

// Resultat :
// {
//   "uuid": "art-1",
//   "title": "Soiree jeux",
//   "body": "...",
//   "category": { "uuid": "cat-1", "name": "Jeux", "color": "#C41E3A" },
//   "tags": [{ "uuid": "t-1" }, { "uuid": "t-2" }]
// }
```
