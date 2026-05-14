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