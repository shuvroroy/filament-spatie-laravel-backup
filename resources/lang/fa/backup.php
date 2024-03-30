<?php

return [

    'components' => [
        'backup_destination_list' => [
            'table' => [
                'actions' => [
                    'download' => 'دانلود',
                    'delete' => 'حذف',
                ],

                'fields' => [
                    'path' => 'مسیر',
                    'disk' => 'دیسک',
                    'date' => 'تاریخ',
                    'size' => 'حجم',
                ],

                'filters' => [
                    'disk' => 'دیسک',
                ],
            ],
        ],

        'backup_destination_status_list' => [
            'table' => [
                'fields' => [
                    'name' => 'نام',
                    'disk' => 'دیسک',
                    'healthy' => 'سالم',
                    'amount' => 'تعداد',
                    'newest' => 'جدیدترین',
                    'used_storage' => 'فضای استفاده شده',
                ],
            ],
        ],
    ],

    'pages' => [
        'backups' => [
            'actions' => [
                'create_backup' => 'ایجاد پشتیبان',
            ],

            'heading' => 'پشتیبان‌ها',

            'messages' => [
                'backup_success' => 'در حال ایجاد پشتیبان در پس‌زمینه.',
                'backup_delete_success' => 'در حال حذف این پشتیبان در پس‌زمینه.',
            ],

            'modal' => [
                'buttons' => [
                    'only_db' => 'فقط دیتابیس',
                    'only_files' => 'فقط فایل‌ها',
                    'db_and_files' => 'فایل‌ها و دیتابیس',
                ],

                'label' => 'یک گزینه را انتخاب کنید',
            ],

            'navigation' => [
                'group' => 'تنظیمات',
                'label' => 'پشتیبان‌ها',
            ],
        ],
    ],

];
