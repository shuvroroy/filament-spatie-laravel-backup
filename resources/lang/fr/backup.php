<?php

return [

    'components' => [
        'backup_destination_list' => [
            'table' => [
                'actions' => [
                    'download' => 'Télécharger',
                    'delete'   => 'Supprimer',
                ],

                'fields' => [
                    'path' => 'Chemin',
                    'disk' => 'Disque',
                    'date' => 'Date',
                    'size' => 'Taille',
                ],

                'filters' => [
                    'disk' => 'Disque'
                ],
            ]
        ],

        'backup_destination_status_list' => [
            'table' => [
                'fields' => [
                    'name'         => 'Nom',
                    'disk'         => 'Disque',
                    'healthy'      => 'Santé',
                    'amount'       => 'Montant',
                    'newest' 	   => 'Récent',
                    'used_storage' => 'Stockage utilisé',
                ],
            ]
        ],
    ],

    'pages' => [
        'backups' => [
            'actions' => [
                'create_backup' => 'Créer une sauvegarde',
            ],

            'heading' => 'Sauvegardes',

            'messages' => [
                'backup_success' => 'Création d\'une nouvelle sauvegarde en arrière-plan.'
            ],

            'modal' => [
                'buttons' => [
                    'only_db'      => 'Seulement la BDD',
                    'only_files'   => 'Seulement les fichiers',
                    'db_and_files' => 'BDD & Fichiers',
                ],

                'label' => 'Veuillez choisir une option',
            ],

            'navigation' => [
                'group' => 'Paramètres',
                'label' => 'Sauvegardes',
            ],
        ],
    ],

];
