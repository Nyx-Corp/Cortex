# Templates JSON — API via Twig

Le même controller sert HTML et JSON. Le `ControllerSubscriber` résout le template par format.

## Principe

```
Controller retourne ['collection' => [...], 'form' => $decorator]
    ↓
ControllerSubscriber: _format=json → shooting/index.json.twig
    ↓
Template hérite de @_theme/layout/list.json.twig
    ↓
JSON avec data, _links, meta (pagination), filters
```

## Templates base

### `list.json.twig`

Pour les listes avec pagination, liens et filtres. Hériter et surcharger `item_fields` :

```twig
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

Structure automatique :
- `data` : array d'items
- `meta` : `page`, `perPage`, `total` (depuis le Pager du ModelQueryDecorator)
- `_links` : `self`, `next`, `prev` (navigation)
- `filters` : filtres disponibles (depuis `filters_config` du décorateur)
- `quota` : limites API (si `_api_quota` est dans les request attributes)

### `response.json.twig`

Pour les réponses des opérations d'écriture (create, update, archive) :

```twig
{% extends '@_theme/layout/response.json.twig' %}

{% block response_fields %}
"uuid": "{{ response.shooting.uuid }}",
"name": "{{ response.shooting.name }}"
{% endblock %}

{% block response_links %}
"self": "{{ path('studio/shooting/edit', {uuid: response.shooting.uuid}) }}"
{% endblock %}
```

## Content-Type

Le `ControllerSubscriber` détecte le format depuis :
1. L'attribut de requête `_format` (si défini dans la route)
2. L'header `Accept` de la requête

Le `Content-Type` de la réponse est automatiquement défini :
- `json` → `application/json`
- `csv` → `text/csv`
- `html` → pas de changement (défaut)

## Fallback

Si aucun template `.json.twig` n'existe pour une route, le `ControllerSubscriber` retourne un `JsonResponse` brut avec le result du controller.

## Subpath fallback

Si un template avec subpath n'existe pas (ex: `catalog/admin/product/edit.html.twig`), le système essaye aussi sans le subpath (`catalog/product/edit.html.twig`). Permet de partager des templates entre variantes applicatives.

## Quotas API

Injecter `_api_quota` dans les request attributes via un subscriber ou middleware :

```php
$request->attributes->set('_api_quota', new QuotaInfo(
    remaining: 95,
    limit: 100,
));
```

Le template `list.json.twig` le rend automatiquement si présent.
