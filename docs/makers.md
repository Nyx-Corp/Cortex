# Cortex Makers

Générateurs de code pour l'architecture DDD. Ces commandes permettent de créer rapidement des structures complètes en respectant les standards du projet.

## Principe

Les makers génèrent du code à partir de templates situés dans `src/Bridge/Symfony/Bundle/Resources/maker/`. Les placeholders suivants sont remplacés automatiquement :

| Placeholder | Description | Exemple |
|-------------|-------------|---------|
| `{Module}` | Nom du module (PascalCase) | `Admin` |
| `{module}` | Nom du module (snake_case) | `admin` |
| `{Domain}` | Nom du domaine (PascalCase) | `Contact` |
| `{domain}` | Nom du domaine (snake_case) | `contact` |
| `{Model}` | Nom du modèle (PascalCase) | `Person` |
| `{model}` | Nom du modèle (snake_case) | `person` |
| `{Action}` | Nom de l'action (PascalCase) | `Archive` |
| `{action}` | Nom de l'action (snake_case) | `archive` |

---

## make:cortex:model

Crée un modèle DDD complet avec sa factory, collection, store et exceptions.

```bash
./bin/php bin/console make:cortex:model {Domain} {Model}
./bin/php bin/console make:cortex:model {Domain} {Model} --dbal  # Avec mapper Doctrine
```

### Fichiers générés

```
src/Domain/{Domain}/
├── Model/{Model}.php              # Entité métier
├── Collection/{Model}Collection.php
├── Factory/{Model}Factory.php
├── Store/{Model}Store.php
└── Error/{Model}Exception.php
```

Avec `--dbal` :

```
src/Infrastructure/Doctrine/{Domain}/
└── Dbal{Model}Mapper.php          # Mapper DBAL

migrations/
└── Version{datetime}.php          # Migration Doctrine
```

---

## make:cortex:action

Crée une action DDD (Command/Handler/Response) avec son controller optionnel.

```bash
./bin/php bin/console make:cortex:action {Module} {Domain} {Model} {Action}
./bin/php bin/console make:cortex:action {Module} {Domain} {Model} {Action} --controller=form
./bin/php bin/console make:cortex:action {Module} {Domain} {Model} {Action} --controller=list
./bin/php bin/console make:cortex:action {Module} {Domain} {Model} {Action} --mcp-tool
```

### Options

| Option | Description |
|--------|-------------|
| `--controller=model` | Controller standard (défaut) |
| `--controller=form` | Controller avec formulaire |
| `--controller=list` | Controller de liste |
| `--mcp-tool` | Génère un MCP Tool wrapper |

### Fichiers générés

**Base (toujours générés) :**

```
src/Domain/{Domain}/
├── Error/{Model}Exception.php
├── Event/{Model}Event.php           # Événement de base du modèle
└── Action/{Model}{Action}/
    ├── Command.php
    ├── Event.php                    # Événement de l'action
    ├── Exception.php
    ├── Handler.php                  # Avec émission d'événement
    └── Response.php
```

**Avec `--controller=model` :**

```
src/Application/{Module}/Controller/Action/
└── {Model}{Action}Action.php
```

**Avec `--controller=form` :**

```
src/Application/{Module}/
├── Controller/Action/{Model}{Action}FormAction.php
└── Form/{Model}{Action}Type.php
```

**Avec `--controller=list` :**

```
src/Application/{Module}/Controller/Action/
└── {Model}ListAction.php
```

**Avec `--mcp-tool` :**

```
src/Application/{Module}/Controller/Tool/
└── {Model}{Action}Tool.php
```

---

## make:cortex:crud

Crée un CRUD complet (List, Edit, Archive) avec templates et tests.

```bash
./bin/php bin/console make:cortex:crud {Module} {Domain} {Model}
./bin/php bin/console make:cortex:crud {Module} {Domain} {Model} --mcp-tool
```

### Options

| Option | Description |
|--------|-------------|
| `--mcp-tool` | Génère les MCP Tools (List, Create, Edit, Archive) |

### Fichiers générés

**Domain :**

```
src/Domain/{Domain}/
├── Event/
│   └── {Model}Event.php             # Événement de base du modèle
└── Action/
    ├── {Model}Edit/
    │   ├── Command.php
    │   ├── Event.php                # Événement de l'action
    │   ├── Exception.php
    │   ├── Handler.php              # Avec émission d'événement
    │   └── Response.php
    └── {Model}Archive/
        ├── Command.php
        ├── Event.php                # Événement de l'action
        ├── Handler.php              # Avec émission d'événement
        └── Response.php
```

**Application :**

```
src/Application/{Module}/
├── Controller/Action/
│   ├── {Model}ListAction.php
│   ├── {Model}EditAction.php
│   └── {Model}ArchiveAction.php
└── Form/
    └── {Model}EditType.php
```

**Templates :**

```
templates/{module}/
├── _layout.html.twig
└── {model}/
    ├── _layout.html.twig
    ├── _form.html.twig
    ├── index.html.twig
    ├── create.html.twig
    └── edit.html.twig
```

**Tests :**

```
tests/Functional/Application/{Module}/Controller/
└── {Model}ControllerTest.php
```

**Avec `--mcp-tool` :**

```
src/Application/{Module}/Controller/Tool/
├── {Model}ListTool.php      # Lister avec filtres/pagination
├── {Model}CreateTool.php    # Créer un nouveau modèle
├── {Model}EditTool.php      # Modifier un modèle existant (uuid requis)
└── {Model}ArchiveTool.php   # Archiver/restaurer
```

---

## make:cortex:command

Crée une commande Symfony.

```bash
./bin/php bin/console make:cortex:command {Module} {Command}
```

### Fichiers générés

```
src/Application/{Module}/Command/
└── {Command}Command.php
```

---

## Workflow recommandé

1. **Créer le modèle** (obligatoire avant le CRUD) :
   ```bash
   ./bin/php bin/console make:cortex:model contact person --dbal
   ```

2. **Créer le CRUD** :
   ```bash
   ./bin/php bin/console make:cortex:crud admin contact person
   ```

3. **Ajouter des actions métier** :
   ```bash
   ./bin/php bin/console make:cortex:action admin contact person validate --controller=model
   ```

---

## MCP Tools

Les MCP Tools permettent d'exposer les actions métier via le protocole MCP (Model Context Protocol) pour l'intégration avec des agents IA.

Le projet utilise le bundle officiel `symfony/mcp-bundle`.

### Configuration

```yaml
# config/packages/mcp.yaml
mcp:
    app: 'bridgeit'
    version: '1.0.0'
    description: 'BridgeIt MCP Server'
    client_transports:
        stdio: true
        http: true
    discovery:
        scan_dirs:
            - 'src'
```

### Endpoint

- **HTTP** : `https://api.bridgeit-app.test/_mcp`
- **STDIO** : `./bin/php bin/console mcp:server`

### Structure d'un Tool

```php
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;

#[McpTool(name: 'contact-person-edit', description: 'Create or update a Person')]
class PersonEditTool
{
    public function __construct(
        private readonly Handler $handler,
        private readonly PersonFactory $factory,
    ) {
    }

    public function __invoke(
        #[Schema(description: 'UUID of the Person (null for creation)')]
        ?string $uuid = null,
    ): array {
        // ...
    }
}
```

### Convention de nommage

Le nom du tool suit le format : `{domain}-{model}-{action}` en kebab-case.

**Tools CRUD générés :**

| Tool | Description | Paramètre clé |
|------|-------------|---------------|
| `{domain}-{model}-list` | Lister avec filtres/pagination | `page`, `limit` |
| `{domain}-{model}-create` | Créer un nouveau modèle | champs requis |
| `{domain}-{model}-edit` | Modifier un modèle existant | `uuid` (requis) |
| `{domain}-{model}-archive` | Archiver/restaurer | `uuid`, `archive` |

Exemples :
- `contact-contact-list`
- `contact-contact-create`
- `contact-contact-edit`
- `contact-contact-archive`

### Debug

Le profiler Symfony inclut un panel MCP pour visualiser les tools, prompts et resources enregistrés.

```bash
# Démarrer le serveur MCP en mode STDIO
./bin/php bin/console mcp:server

# Tester l'endpoint HTTP
curl -X POST https://api.bridgeit-app.test/_mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":1}'
```
