# UPGRADE 2.1 → 2.2

## Pagination opt-in (breaking change)

`ModelQuery` n'initialise plus de `Pager` par defaut. Les requetes programmatiques retournent **tous les resultats** sans pagination implicite.

Les pages admin ne sont **pas impactees** : le `ModelQueryDecorator` pose automatiquement un pager depuis les parametres HTTP.

### Avant (v2.1)

```php
// Pagination implicite : LIMIT 20 OFFSET 0, COUNT automatique
$results = $factory->query()
    ->filter(status: 'active')
    ->getCollection();

// Pour tout recuperer, il fallait desactiver explicitement
$all = $factory->query()
    ->filter(status: 'active')
    ->paginate(null)
    ->getCollection();
```

### Apres (v2.2)

```php
// Pas de pagination : retourne TOUT
$all = $factory->query()
    ->filter(status: 'active')
    ->getCollection();

// Pour paginer, appel explicite
$paginated = $factory->query()
    ->filter(status: 'active')
    ->paginate(new Pager(page: 1, nbPerPage: 20))
    ->getCollection();
```

### Migration

1. **Chercher `->paginate(null)`** dans votre code : ces appels sont devenus inutiles, supprimez-les.

```bash
grep -rn '->paginate(null)' src/
```

2. **Chercher les requetes programmatiques** qui reposaient sur la pagination implicite (rare) : si du code attendait 20 resultats max sans appel explicite a `paginate()` ou `limit()`, ajoutez `->paginate(new Pager(1))` ou `->limit(20)`.

3. **Les list controllers admin** passant par `ModelQueryDecorator` ne sont pas impactes — le Decorator pose le pager automatiquement depuis `?page=` et `?limit=`.

---

## FilterQueryParser

Nouvel utilitaire pour parser des query strings Gmail-style.

### Usage

```php
// Directement sur un ModelQuery (raccourci fluent)
$results = $factory->query()
    ->applyFilterQuery('type:page status:published sort:name_asc limit:5', [
        'type'   => 'content_type',
        'status' => 'status',
    ])
    ->getCollection();

// Ou via l'objet parser
$parser = new FilterQueryParser(defaultLimit: 20);
$parsed = $parser->parse('type:page sort:name_desc');
```

Voir `docs/filter-query-parser.md` pour la documentation complete.

---

## CurrentDateFactory / CurrentDate — suppression

Classes supprimees :
- `Cortex\ValueObject\CurrentDateFactory`
- `Cortex\ValueObject\CurrentDate`
- `Cortex\Component\Date\DateTimeFactory`

Migration : utiliser `ClockInterface` de Symfony + `Clock::get()->now()`. Dans les tests, utiliser `ClockSensitiveTrait`.

### Avant

```php
use Cortex\ValueObject\CurrentDateFactory;

public function __construct(private CurrentDateFactory $dateFactory) {}

public function execute(): void
{
    $now = ($this->dateFactory)();
}
```

### Apres

```php
use Psr\Clock\ClockInterface;

public function __construct(private ClockInterface $clock) {}

public function execute(): void
{
    $now = $this->clock->now();
}
```

### Migration

1. Chercher `CurrentDateFactory` dans `services.yaml` et le code source, supprimer ou remplacer.
2. Injecter `ClockInterface` a la place.
3. Dans les tests, utiliser `ClockSensitiveTrait` pour mocker l'heure.

---

## JoinDefinition — FK par convention

`JoinDefinition` detecte maintenant automatiquement la FK selon la convention `{relationName}_uuid`. Le parametre `localKey` n'est plus obligatoire.

Breaking : `getLocalKey()` retourne desormais la cle conventionnelle quand `localKey` est null (comportement indefini auparavant).

Nouveautes :
- `withRelationName(string)` — retourne une copie avec le nom de relation (utilise par `DbalMappingConfiguration`)
- `withParentAlias(string)` — pour les jointures imbriquees
- Les collections (`AsyncCollection`) sont exclues automatiquement de la decouverte de colonnes
- Les types classe sont auto-detectes comme relations (sauf VOs, enums, dates, UIDs)

### Avant

```php
new JoinDefinition(
    factory: $this->orgFactory,
    joinConfig: $this->orgMapper->getConfiguration(),
    localKey: 'organisation_uuid',  // obligatoire
)
```

### Apres

```php
// Convention : FK = {relation}_uuid — localKey auto-detecte
new JoinDefinition(
    factory: $this->orgFactory,
    joinConfig: $this->orgMapper->getConfiguration(),
)
```

### Migration

Si votre `localKey` existant correspond deja a la convention `{relation}_uuid`, supprimez-le.

---

## PHPUnit 11+ requis

Les tests utilisent desormais les attributs PHP natifs au lieu des annotations docblock.

### Avant

```php
/**
 * @covers \MyClass
 * @dataProvider myProvider
 */
```

### Apres

```php
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(MyClass::class)]
// ...
#[DataProvider('myProvider')]
```

### Migration

Lancer `vendor/bin/rector` avec le ruleset PHPUnit 11, ou remplacer les annotations manuellement.
