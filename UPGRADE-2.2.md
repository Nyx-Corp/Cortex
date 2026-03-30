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
