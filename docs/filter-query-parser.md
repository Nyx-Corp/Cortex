# FilterQueryParser

Utilitaire standalone pour parser des query strings Gmail-style en parametres de requete structures.

**Namespace** : `Cortex\Component\Model\Query\FilterQueryParser`

## Syntaxe

```
field:value field2:"value with spaces" sort:name_desc limit:5
```

- **Filtres** : `field:value` — collectes comme paires cle/valeur
- **Sort** : `sort:field_asc` ou `sort:field_desc` — parse en `Sorter` (defaut ASC si pas de suffixe)
- **Limit** : `limit:N` — entier cape entre 1 et max (defaut 10, max 100)
- **Valeurs avec espaces** : `field:"ma valeur"` — guillemets doubles

## Usage rapide — ModelQuery::applyFilterQuery()

La facon la plus simple d'utiliser le parser : directement sur un `ModelQuery` :

```php
$results = $factory->query()
    ->applyFilterQuery('type:page status:published sort:name_asc limit:5', [
        'type'    => 'content_type',
        'status'  => 'status',
        'channel' => 'channel_name',
    ])
    ->getCollection();
```

La methode :
1. Parse la query string
2. Mappe les champs via le `$fieldMap` (champs inconnus ignores)
3. Applique filtres, sort, et limit sur le query
4. Retourne `$this` (fluent)

Si `$fieldMap` est vide, les noms de champs sont utilises tels quels.

## Usage bas-niveau — FilterQueryParser

Pour un controle plus fin :

```php
use Cortex\Component\Model\Query\FilterQueryParser;

$parser = new FilterQueryParser();
$parsed = $parser->parse('type:page status:published sort:name_asc limit:5');

$parsed->filters;  // ['type' => 'page', 'status' => 'published']
$parsed->sort;     // Sorter('name', ASC)
$parsed->limit;    // 5

// Mapper les champs
$dbFilters = $parsed->mapFilters([
    'type'    => 'content_type',
    'status'  => 'status',
]);
// → ['content_type' => 'page', 'status' => 'published']
```

### Options du parser

```php
$parser = new FilterQueryParser(
    defaultLimit: 20,   // Limit si absent de la query (defaut: 10)
    maxLimit: 200,       // Plafond du limit (defaut: 100)
);
$parsed = $parser->parse('type:page limit:50');
```

## ParsedFilterQuery

Objet retourne par `parse()` :

| Propriete | Type | Description |
|-----------|------|-------------|
| `filters` | `array<string, string>` | Paires champ/valeur brutes |
| `sort` | `?Sorter` | Directive de tri, ou null |
| `limit` | `int` | Limite capee |
| `mapFilters(array $fieldMap)` | `array<string, string>` | Filtre mappe vers les colonnes DB |

## Relation avec ModelQueryDecorator

`ModelQueryDecorator::parseQueryString()` fait un travail similaire mais est :
- Private et couple au systeme de formulaires
- Valide les champs contre les filtres declares

`FilterQueryParser` est standalone, reutilisable partout (block controllers, CLI, tests).
`ModelQuery::applyFilterQuery()` est le raccourci fluent pour l'usage courant.
