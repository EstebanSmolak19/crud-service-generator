<?php

return [

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

        // Permet de cacher le dossier 'app/Models/Base' dans l'éditeur VSCode.
        'hide_base_models_in_vscode' => true,
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
];
