<?php

return [

    'components' => [
        'backup_destination_list' => [
            'table' => [
                'actions' => [
                    'download' => 'Download',
                    'delete' => 'Löschen',
                ],

                'fields' => [
                    'path' => 'Pfad',
                    'disk' => 'Speicher',
                    'date' => 'Datum',
                    'size' => 'Größe',
                ],

                'filters' => [
                    'disk' => 'Speicher',
                ],
            ],
        ],

        'backup_destination_status_list' => [
            'table' => [
                'fields' => [
                    'name' => 'Name',
                    'disk' => 'Speicher',
                    'healthy' => 'Gesund',
                    'amount' => 'Anzahl',
                    'newest' => 'Neuestes',
                    'used_storage' => 'Verwendeter Speicher',
                ],
            ],
        ],
    ],

    'pages' => [
        'backups' => [
            'actions' => [
                'create_backup' => 'Sicherung erstellen',
            ],

            'heading' => 'Backups',

            'messages' => [
                'backup_success' => 'Erstelle eine neue Sicherung im Hintergrund.',
                'backup_delete_success' => 'Lösche die Sicherung im Hintergrund.',
            ],

            'modal' => [
                'buttons' => [
                    'only_db' => 'Nur DB',
                    'only_files' => 'Nur Dateien',
                    'db_and_files' => 'DB & Dateien',
                ],

                'label' => 'Bitte eine Option auswählen',
            ],

            'navigation' => [
                'group' => 'Einstellungen',
                'label' => 'Sicherungen',
            ],
        ],
    ],

];
