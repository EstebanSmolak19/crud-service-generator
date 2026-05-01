<?php

return [

    // Chemain par défaut de la création d'un service
    'path' => 'app/Services',
    'stict_types' => true,

    'models' => [
         // Les tables à exclure.
        'whitelist' => [
            ''
        ],

        // Champs des tables à exclure
        'excluded_columns' => [
            ''
        ],
    ],
];
