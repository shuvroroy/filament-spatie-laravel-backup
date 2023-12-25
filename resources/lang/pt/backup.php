<?php

return [

    'components' => [
        'backup_destination_list' => [
            'table' => [
                'actions' => [
                    'download' => 'Download',
                    'delete' => 'Eliminar',
                ],

                'fields' => [
                    'path' => 'Caminho',
                    'disk' => 'Disco',
                    'date' => 'Data',
                    'size' => 'Tamanho',
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
                    'healthy' => 'Estado',
                    'amount' => 'Quantidade',
                    'newest' => 'Recente',
                    'used_storage' => 'Espaço Utilizado',
                ],
            ],
        ],
    ],

    'pages' => [
        'backups' => [
            'actions' => [
                'create_backup' => 'Criar Cópia de Segurança',
            ],

            'heading' => 'Cópias de Segurança',

            'messages' => [
                'backup_success' => 'A criar uma nova cópia de segurança em segundo plano.',
                'backup_delete_success' => 'A eliminar esta cópia de segurança em segundo plano.',
            ],

            'modal' => [
                'buttons' => [
                    'only_db' => 'Apenas BD',
                    'only_files' => 'Apenas Ficheiros',
                    'db_and_files' => 'BD & Ficheiros',
                ],

                'label' => 'Por favor, seleccione uma opção',
            ],

            'navigation' => [
                'group' => 'Definições',
                'label' => 'Cópias de Segurança',
            ],
        ],
    ],

];
