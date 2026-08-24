<?php

return [

    'components' => [
        'backup_destination_list' => [
            'table' => [
                'actions' => [
                    'download' => 'Завантажити',
                    'delete' => 'Видалити',
                ],

                'fields' => [
                    'path' => 'Шлях',
                    'disk' => 'Диск',
                    'date' => 'Дата',
                    'size' => 'Розмір',
                ],

                'filters' => [
                    'disk' => 'Диск',
                    'type' => 'Тип резервної копії',
                ],
            ],
        ],

        'backup_destination_status_list' => [
            'table' => [
                'fields' => [
                    'name' => 'Назва',
                    'disk' => 'Диск',
                    'healthy' => 'Справний',
                    'amount' => 'Кількість',
                    'newest' => 'Остання',
                    'used_storage' => 'Використано сховища',
                    'no_backups_present' => 'Резервних копій немає',
                ],
            ],
        ],
    ],

    'pages' => [
        'backups' => [
            'actions' => [
                'create_backup' => 'Створити резервну копію',
            ],

            'heading' => 'Резервні копії',

            'messages' => [
                'backup_success' => 'Створення нової резервної копії у фоновому режимі.',
                'backup_delete_success' => 'Резервну копію успішно видалено.',
            ],

            'modal' => [
                'buttons' => [
                    'only_db' => 'Лише база даних',
                    'only_files' => 'Лише файли',
                    'db_and_files' => 'База даних і файли',
                ],

                'label' => 'Будь ласка, виберіть варіант',
            ],

            'navigation' => [
                'group' => 'Налаштування',
                'label' => 'Резервні копії',
            ],
        ],
    ],

];
