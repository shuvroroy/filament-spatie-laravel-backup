<?php

return [

    'components' => [
        'backup_destination_list' => [
            'table' => [
                'actions' => [
                    'download' => 'Download',
                    'delete' => 'Cancella',
                ],

                'fields' => [
                    'path' => 'Percorso',
                    'disk' => 'Disco',
                    'date' => 'Data',
                    'size' => 'Dimensione',
                ],

                'filters' => [
                    'disk' => 'Disco',
                ],
            ],
        ],

        'backup_destination_status_list' => [
            'table' => [
                'fields' => [
                    'name' => 'Nome',
                    'disk' => 'Disco',
                    'healthy' => 'Salute',
                    'amount' => 'Quantità',
                    'newest' => 'Più recente',
                    'used_storage' => 'Storage Utilizzato',
                ],
            ],
        ],
    ],

    'pages' => [
        'backups' => [
            'actions' => [
                'create_backup' => 'Crea Backup',
            ],

            'heading' => 'Backup',

            'messages' => [
                'backup_success' => 'Creazione di un nuovo backup in background.',
                'backup_delete_success' => 'Eliminazione di questo backup in background.',
            ],

            'modal' => [
                'buttons' => [
                    'only_db' => 'Solo DB',
                    'only_files' => 'Solo File',
                    'db_and_files' => 'DB & File',
                ],

                'label' => "Scegli un'opzione",
            ],

            'navigation' => [
                'group' => 'Impostazioni',
                'label' => 'Backup',
            ],
        ],
    ],

];
