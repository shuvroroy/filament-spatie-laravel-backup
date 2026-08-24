<?php

return [

    'components' => [
        'backup_destination_list' => [
            'table' => [
                'actions' => [
                    'download' => 'Download',
                    'delete' => 'Excluir',
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
                    'healthy' => 'Saúde',
                    'amount' => 'Quant.',
                    'newest' => 'Recente',
                    'used_storage' => 'Espaço utilizado',
                    'no_backups_present' => 'Nenhum backup presente',
                ],
            ],
        ],
    ],

    'pages' => [
        'backups' => [
            'actions' => [
                'create_backup' => 'Criar Backup',
            ],

            'heading' => 'Backups',

            'messages' => [
                'backup_success' => 'Criando um novo em segundo plano.',
                'backup_delete_success' => 'O backup foi excluído com sucesso.',
            ],

            'modal' => [
                'buttons' => [
                    'only_db' => 'Apenas DB',
                    'only_files' => 'Apenas arquivos',
                    'db_and_files' => 'DB & Arquivos',
                ],

                'label' => 'Por favor, escolha uma opção',
            ],

            'navigation' => [
                'group' => 'Configurações',
                'label' => 'Backups',
            ],
        ],
    ],

];
