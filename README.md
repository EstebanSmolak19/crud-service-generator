# CRUD Service Generator — Documentation

**CRUD Service Generator** is a Laravel package that automates the creation of robust, secure and configurable CRUD services, based on **PHP 8 Attributes** and a built-in **Audit** system.

---

## 📦 Installation

```bash
composer require estebansmolak19/crud-service-generator

php artisan vendor:publish --tag="crud-service-generator-config"
php artisan vendor:publish --tag="crud-service-generator-migrations"
php artisan migrate
```

---

## 💡 Model Architecture — Double Inheritance

```bash
php artisan generate:model
```

The package synchronizes your models with the database without ever overwriting your code.

| File | Role |
|---|---|
| `App\Models\Base\BaseArret.php` | Auto-generated — **overwritten** on every sync |
| `App\Models\Arret.php` | Your file — generated **once only**, free to modify |

```php
// ✅ App\Models\Arret.php — add your methods here, safely
class Arret extends BaseArret
{
    public function isAdmin(): bool
    {
        return true;
    }
}
```

---

## 🔧 Commands

### `php artisan make:service`

Generates a service and its associated components.

| Option | Effect |
|---|---|
| *(none)* | Generates an empty service |
| `--crud` | Generates a service with CRUD methods |
| `--controller` | Generates a service + API controller |
| `--all` | Generates everything: **CRUD Service + CRUD Controller + Routes + Resource** |

```bash
# Minimal CRUD service
php artisan make:service ArretService --crud

# Generate everything at once
php artisan make:service ArretService --all
```

With `--all`, the generator asks a few interactive questions:
- The associated model (e.g. `Arret`)
- The controller name
- The route prefix (e.g. `arrets` → generates `GET /arrets`, `POST /arrets`, etc.)

Routes are automatically added in `routes/service_generator.php`.

### Other commands

| Command | Description |
|---|---|
| `php artisan make:attribute MyAttribute` | Generates an attribute interceptor |
| `php artisan generate:model` | Synchronizes models with the DB |
| `php artisan config:apply` | Applies the configuration file |
| `php artisan p:help` | Displays the full command guide |

---

## 🏗️ Anatomy of a CRUD Service

```php
class ArretService extends CrudServiceBase implements IFillableContract, HasSqlOverrides
{
    // 🔴 REQUIRED — forgetting this line throws a LogicException at startup
    // role: filters columns exposed via API
    protected array $fillable = ['nom', 'latitude', 'longitude'];

    // Options available via HasCrudConfiguration (all optional)
    protected bool  $audit   = true;             // Enables audit logs
    protected array $orderBy = ['nom' => 'ASC']; // Default sort
    protected int   $perPage = 15;               // Pagination

    public function __construct(Arret $model)
    {
        parent::__construct($model);
    }

    public function permissions(): array
    {
        return [
            'create'  => [IsAuthenticated::class],
            'update'  => [IsAuthenticated::class],
            'destroy' => [IsAuthenticated::class, IsAdmin::class],
            // Missing keys = public access (e.g. 'all', 'find')
        ];
    }
}
```

---

## 📋 CRUD Methods

| Method | Parameters | Description |
|---|---|---|
| `all()` | — | Returns all records, paginated according to `$perPage` |
| `find($id)` | `mixed $id` | Returns a record by ID — `404` if not found |
| `create($data)` | `array $data` | Creates a record, logs if `$audit = true` |
| `update($id, $data)` | `mixed $id`, `array $data` | Updates, logs `old_values` / `new_values` if audit |
| `destroy($id)` | `mixed $id` | Deletes, logs before deletion if audit, returns `bool` |

---

## 🔒 The Attribute System

Attributes allow you to attach logic (security, validation...) to a method or an entire service, **without polluting the business logic**.

Before each call, the package automatically inspects the declared attributes and executes them in order. If an attribute calls `abort()`, execution stops immediately.

### Included attribute: `IsAuthenticated`

Blocks with an `HTTP 401` if the user is not logged in.

```php
// A. Via permissions() — for standard CRUD methods (all, find, create, update, destroy)
public function permissions(): array
{
    return [
        'create'  => [IsAuthenticated::class],
        'destroy' => [IsAuthenticated::class, IsAdmin::class], // All must pass
    ];
}

// B. Via PHP 8 Attribute — for your custom methods
#[IsAuthenticated]
#[IsAdmin]
public function exportData(): array { ... }
```

### Creating your own Attribute

```bash
php artisan make:attribute IsAdmin
```

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class IsAdmin implements ServiceAttributeContract
{
    public function handle(object $service, string $method, array &$params): void
    {
        // $service → instance of the called service
        // $method  → name of the intercepted method (e.g. 'create')
        // &$params → passed arguments, modifiable by reference

        if (!auth()->user()?->is_admin) {
            abort(403, "Access restricted to administrators.");
        }
    }
}
```

You can also pass parameters to your attribute:

```php
#[IsRole('super-admin')]
public function deleteAll(): void { ... }

// In the attribute
class IsRole implements ServiceAttributeContract
{
    public function __construct(private string $role)
    {
        // $this->role being the parameter initialized in the constructor.
    }

    public function handle(object $service, string $method, array &$params): void
    {
        if (!auth()->user()?->hasRole($this->role)) {
            abort(403);
        }
    }
}
```

---

## 📋 Audit System and Logs

Enable audit in your service:

```php
protected bool $audit = true;
```

Every `create`, `update`, `destroy` action is automatically recorded in `crud_service_logs`:

| Column | Description | Example |
|---|---|---|
| `user_id` | Who acted | `7` |
| `event` | Action type | `update` |
| `auditable_type` | Model class | `App\Models\Arret` |
| `auditable_id` | Record ID | `42` |
| `old_values` | Snapshot before (JSON) | `{"nom":"Gare Nord"}` |
| `new_values` | Snapshot after (JSON) | `{"nom":"Gare du Nord"}` |

---

## 💾 SQL Overrides (Views and Procedures)

For complex architectures, you can bypass Eloquent.

### SQL Views

```php
// The all() and find() methods will read from this view rather than the table with Eloquent
protected ?string $sqlViewName = 'vue_arrets_avec_ligne';
```

⚠️ The view **must** expose the column defined in `$primaryKey` (default `id`) for `find()` to work.

### Stored Procedures

```php
// Delegate create / update / destroy to SQL procedures
protected ?string $sqlCreateProcedure = 'sp_create_arret';
protected ?string $sqlUpdateProcedure = 'sp_update_arret';
protected ?string $sqlDeleteProcedure = 'sp_delete_arret';
```

---

## 👥 Credits

- [EstebanSmolak19](https://github.com/EstebanSmolak19)

## 📄 License
The MIT License (MIT).

---

---

# CRUD Service Generator — Documentation

**CRUD Service Generator** est un package Laravel qui automatise la création de services CRUD
robustes, sécurisés et configurables, basé sur les **Attributs PHP 8** et un système d'**Audit** intégré.

---

## 📦 Installation

```bash
composer require estebansmolak19/crud-service-generator

php artisan vendor:publish --tag="crud-service-generator-config"
php artisan vendor:publish --tag="crud-service-generator-migrations"
php artisan migrate
```

---

## 💡 Architecture des Modèles — Double Héritage

```bash
php artisan generate:model
```

Le package synchronise vos modèles avec la base de données sans jamais écraser votre code.

| Fichier | Rôle |
|---|---|
| `App\Models\Base\BaseArret.php` | Généré automatiquement — **écrasé** à chaque sync |
| `App\Models\Arret.php` | Votre fichier — généré **une seule fois**, libre à modifier |

```php
// ✅ App\Models\Arret.php — ajoutez vos méthodes ici, en toute sécurité
class Arret extends BaseArret
{
    public function isAdmin(): bool
    {
        return true;
    }
}
```

---

## 🔧 Commandes

### `php artisan make:service`

Génère un service et ses composants associés.

| Option | Effet |
|---|---|
| *(aucune)* | Génère un service vide |
| `--crud` | Génère un service avec les méthodes CRUD |
| `--controller` | Génère un service + contrôleur API |
| `--all` | Génère la totale : **Service CRUD + Controller CRUD + Routes + Resource** |

```bash
# Service CRUD minimal
php artisan make:service ArretService --crud

# Tout générer d'un coup
php artisan make:service ArretService --all
```

Avec `--all`, le générateur vous pose quelques questions interactives :
- Le modèle associé (ex: `Arret`)
- Le nom du contrôleur
- Le préfixe de route (ex: `arrets` → génère `GET /arrets`, `POST /arrets`, etc.)

Les routes sont ajoutées automatiquement dans `routes/service_generator.php`.

### Autres commandes

| Commande | Description |
|---|---|
| `php artisan make:attribute MonAttribut` | Génère un intercepteur d'attribut |
| `php artisan generate:model` | Synchronise les modèles avec la BDD |
| `php artisan config:apply` | Applique le fichier de configuration|
| `php artisan p:help` | Affiche le guide complet des commandes|

---

## 🏗️ Anatomie d'un Service CRUD

```php
class ArretService extends CrudServiceBase implements IFillableContract, HasSqlOverrides
{
    // 🔴 OBLIGATOIRE — oublier cette ligne lève une LogicException au démarrage
    // rôle : filtre des colonnes exposées en API
    protected array $fillable = ['nom', 'latitude', 'longitude'];

    // Options disponibles via HasCrudConfiguration (toutes optionnelles)
    protected bool  $audit   = true;             // Active les logs d'audit
    protected array $orderBy = ['nom' => 'ASC']; // Tri par défaut
    protected int   $perPage = 15;               // Pagination

    public function __construct(Arret $model)
    {
        parent::__construct($model);
    }

    public function permissions(): array
    {
        return [
            'create'  => [IsAuthenticated::class],
            'update'  => [IsAuthenticated::class],
            'destroy' => [IsAuthenticated::class, IsAdmin::class],
            // Les clés absentes = accès public (ex: 'all', 'find')
        ];
    }
}
```

---

## 📋 Méthodes CRUD

| Méthode | Paramètres | Description |
|---|---|---|
| `all()` | — | Retourne tous les enregistrements, paginé selon `$perPage` |
| `find($id)` | `mixed $id` | Retourne un enregistrement par ID — `404` si introuvable |
| `create($data)` | `array $data` | Crée un enregistrement, logue si `$audit = true` |
| `update($id, $data)` | `mixed $id`, `array $data` | Met à jour, logue `old_values` / `new_values` si audit |
| `destroy($id)` | `mixed $id` | Supprime, logue avant suppression si audit, retourne `bool` |

---

## 🔒 Le Système d'Attributs

Les attributs permettent d'attacher de la logique (sécurité, validation...) sur une méthode
ou un service entier, **sans polluer la logique métier**.

Avant chaque appel, le package inspecte automatiquement les attributs déclarés et les exécute
dans l'ordre. Si un attribut fait un `abort()`, l'exécution s'arrête immédiatement.

### Attribut inclus : `IsAuthenticated`

Bloque avec un `HTTP 401` si l'utilisateur n'est pas connecté.

```php
// A. Via permissions() — pour les méthodes CRUD standards (all, find, create, update, destroy)
public function permissions(): array
{
    return [
        'create'  => [IsAuthenticated::class],
        'destroy' => [IsAuthenticated::class, IsAdmin::class], // Tous doivent passer
    ];
}

// B. Via Attribut PHP 8 — pour vos méthodes personnalisées
#[IsAuthenticated]
#[IsAdmin]
public function exportData(): array { ... }
```

### Créer votre propre Attribut

```bash
php artisan make:attribute IsAdmin
```

```php
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class IsAdmin implements ServiceAttributeContract
{
    public function handle(object $service, string $method, array &$params): void
    {
        // $service → instance du service appelé
        // $method  → nom de la méthode interceptée (ex: 'create')
        // &$params → arguments passés, modifiables par référence

        if (!auth()->user()?->is_admin) {
            abort(403, "Accès réservé aux administrateurs.");
        }
    }
}
```

Vous pouvez également passer des paramètres à votre attribut :

```php
#[IsRole('super-admin')]
public function deleteAll(): void { ... }

// Dans l'attribut
class IsRole implements ServiceAttributeContract
{
    public function __construct(private string $role)
    {
        // $this->role étant le paramètre initialisé dans le constructeur.
    }

    public function handle(object $service, string $method, array &$params): void
    {
        if (!auth()->user()?->hasRole($this->role)) {
            abort(403);
        }
    }
}
```

---

## 📋 Système d'Audit et Logs

Activez l'audit dans votre service :

```php
protected bool $audit = true;
```

Chaque action `create`, `update`, `destroy` est enregistrée automatiquement dans
`crud_service_logs` :

| Colonne | Description | Exemple |
|---|---|---|
| `user_id` | Qui a agi | `7` |
| `event` | Type d'action | `update` |
| `auditable_type` | Classe du modèle | `App\Models\Arret` |
| `auditable_id` | ID de l'enregistrement | `42` |
| `old_values` | Snapshot avant (JSON) | `{"nom":"Gare Nord"}` |
| `new_values` | Snapshot après (JSON) | `{"nom":"Gare du Nord"}` |

---

## 💾 Surcharges SQL (Vues et Procédures)

Pour les architectures complexes, vous pouvez court-circuiter Eloquent.

### Vues SQL

```php
// Les méthodes all() et find() liront depuis cette vue plutôt que la table avec Eloquent
protected ?string $sqlViewName = 'vue_arrets_avec_ligne';
```

⚠️ La vue **doit** exposer la colonne définie dans `$primaryKey` (par défaut `id`)
pour que `find()` fonctionne.

### Procédures Stockées

```php
// Déléguer create / update / destroy à des procédures SQL
protected ?string $sqlCreateProcedure = 'sp_create_arret';
protected ?string $sqlUpdateProcedure = 'sp_update_arret';
protected ?string $sqlDeleteProcedure = 'sp_delete_arret';
```

---

## 👥 Credits

- [EstebanSmolak19](https://github.com/EstebanSmolak19)

## 📄 License
The MIT License (MIT).