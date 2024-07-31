<?php

return [

    'components' => [
        'backup_destination_list' => [
            'table' => [
                'actions' => [
                    'download' => '下载',
                    'delete' => '删除',
                ],

                'fields' => [
                    'path' => '路径',
                    'disk' => '磁盘',
                    'date' => '日期',
                    'size' => '大小',
                ],

                'filters' => [
                    'disk' => '磁盘',
                ],
            ],
        ],

        'backup_destination_status_list' => [
            'table' => [
                'fields' => [
                    'name' => '文件名',
                    'disk' => '所属磁盘',
                    'healthy' => '健康状况',
                    'amount' => '大小',
                    'newest' => '最新备份',
                    'used_storage' => '占用空间',
                ],
            ],
        ],
    ],

    'pages' => [
        'backups' => [
            'actions' => [
                'create_backup' => '创建备份',
            ],

            'heading' => '数据备份',

            'messages' => [
                'backup_success' => '在后台创建新备份',
                'backup_delete_success' => '在后台删除此备份',
            ],

            'modal' => [
                'buttons' => [
                    'only_db' => '仅备份数据库',
                    'only_files' => '仅备份文件',
                    'db_and_files' => '备份数据库和文件',
                ],

                'label' => '请选择一个选项',
            ],

            'navigation' => [
                'group' => '系统设置',
                'label' => '数据备份',
            ],
        ],
    ],

];
