<?php

return [

    'components' => [
        'backup_destination_list' => [
            'table' => [
                'actions' => [
                    'download' => 'Stáhnout',
                    'delete' => 'Smazat',
                ],

                'fields' => [
                    'path' => 'Cesta',
                    'disk' => 'Disk',
                    'date' => 'Datum',
                    'size' => 'Velikost',
                ],

                'filters' => [
                    'disk' => 'Disk',
                ],
            ],
        ],

        'backup_destination_status_list' => [
            'table' => [
                'fields' => [
                    'name' => 'Název',
                    'disk' => 'Disk',
                    'healthy' => 'Stav zdraví',
                    'amount' => 'Počet',
                    'newest' => 'Nejnovější',
                    'used_storage' => 'Využité místo',
                ],
            ],
        ],
    ],

    'pages' => [
        'backups' => [
            'actions' => [
                'create_backup' => 'Vytvořit zálohu',
            ],

            'heading' => 'Zálohy',

            'messages' => [
                'backup_success' => 'Nová záloha byla úspěšně vytvořena.',
                'backup_delete_success' => '"Záloha byla úspěšně odstraněna.',
            ],

            'modal' => [
                'buttons' => [
                    'only_db' => 'Pouze databáze',
                    'only_files' => 'Pouze soubory',
                    'db_and_files' => 'Databáze a soubory',
                ],

                'label' => 'Vyberte prosím jednu z možností',
            ],

            'navigation' => [
                'group' => 'Nastavení',
                'label' => 'Zálohy',
            ],
        ],
    ],

];
