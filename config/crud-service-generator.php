<?php

return [

    /*
    |--------------------------------------------------------------------------
    | UUIDs Configuration
    |--------------------------------------------------------------------------
    |
    | Cette section gère le format des identifiants utilisés pour la traçabilité
    | et les tables de logs du package.
    |
    | 'use_uuids' : Définissez à true si vos modèles utilisent des UUIDs
    |               au lieu d'entiers auto-incrémentés, afin d'adapter automatiquement
    |               les colonnes morphiques et les relations de logs.
    |
    */

    'use_uuids' => false,

    /*
    |--------------------------------------------------------------------------
    | Service Path
    |--------------------------------------------------------------------------
    |
    | Ce chemin définit l'endroit où vos services seront générés par défaut.
    | Vous pouvez modifier cette valeur pour l'adapter à l'architecture
    | de votre application, par exemple 'app/Domain/Services'.
    |
    */

    'path' => 'app/Services',

    /*
    |--------------------------------------------------------------------------
    | Strict Types
    |--------------------------------------------------------------------------
    |
    | Si cette option est activée, le générateur ajoutera 'declare(strict_types=1);'
    | au début de chaque fichier PHP généré. C'est une excellente pratique
    | pour garantir la robustesse de votre code.
    |
    */

    'strict_types' => true,

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    |
    | Ici, vous pouvez configurer le comportement du générateur vis-à-vis
    | de vos modèles Eloquent. La whitelist permet de limiter la génération
    | à certaines tables spécifiques.
    |
    */

    'models' => [
        // Tables à exclure (laisse vide pour tout inclure)
        'whitelist' => [
            'cache', 'cache_locks', 'failed_jobs',
            'job_batches', 'jobs', 'migrations',
            'password_reset_tokens', 'sessions',
            'personal_access_tokens',
            config('crud-service-generator.database.table_name_log'),
        ],

        // Colonnes à ignorer systématiquement lors de la génération des models
        'excluded_columns' => [
            'id', 'password', 'remember_token', 'created_at', 'updated_at', 'deleted_at',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination Settings
    |--------------------------------------------------------------------------
    |
    | Ces options contrôlent le système de pagination automatique de vos services.
    | 'default' définit le nombre d'items par page si rien n'est précisé,
    | tandis que 'max' empêche de surcharger le serveur via l'URL.
    |
    */

    'pagination' => [
        'default_per_page' => env('CRUD_DEFAULT_PAGINATION', 5),
        'max_per_page' => env('CRUD_DEFAULT_MAX_PAGINATION', 100),
        'param_name' => env('CRUD_QUERY_NAME', 'per_page'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Table Name
    |--------------------------------------------------------------------------
    |
    | Nom de la table utilisée pour stocker les logs d'audit.
    | Par défaut : 'crud_service_logs'.
    |
    */
    'database' => [
        'table_name_log' => 'crud_service_logs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Messages
    |--------------------------------------------------------------------------
    |
    | Ces messages sont utilisés pour les réponses JSON de l'API lorsqu'une
    | erreur survient (par exemple, une erreur d'authentification).
    | Vous pouvez les personnaliser selon vos besoins. Veillez à conserver
    | le mot-clé ':method' pour que le nom de la méthode reste dynamique.
    |
    */
    'messages' => [
        'unauthorized' => 'Non autorisé.',
        'unauthorized_detail' => 'Authentification requise pour accéder à la méthode : :method',
    ],

   /*
    |--------------------------------------------------------------------------
    | Frontend Model Generator
    |--------------------------------------------------------------------------
    |
    | Cette section gère la configuration pour la génération automatique
    | des interfaces TypeScript destinées au frontend (React, Vue, Inertia, etc.).
    |
    | 'path' : Définit le chemin absolu ou relatif où le fichier .ts
    |          du modèle sera généré dans l'application.
    |
    */

    'frontend' => [
        'path' => base_path('resources/js/types'),
    ],
];
