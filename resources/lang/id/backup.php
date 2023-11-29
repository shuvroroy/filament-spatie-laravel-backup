<?php

return [

    'components' => [
        'backup_destination_list' => [
            'table' => [
                'actions' => [
                    'download' => 'Unduh',
                    'delete' => 'Hapus',
                ],

                'fields' => [
                    'path' => 'Lokasi',
                    'disk' => 'Penyimpanan',
                    'date' => 'Tanggal',
                    'size' => 'Ukuran',
                ],

                'filters' => [
                    'disk' => 'Penyimpanan',
                ],
            ],
        ],

        'backup_destination_status_list' => [
            'table' => [
                'fields' => [
                    'name' => 'Nama',
                    'disk' => 'Penyimpanan',
                    'healthy' => 'Sehat',
                    'amount' => 'Jumlah',
                    'newest' => 'Terbaru',
                    'used_storage' => 'Penyimpanan Terpakai',
                ],
            ],
        ],
    ],

    'pages' => [
        'backups' => [
            'actions' => [
                'create_backup' => 'Buat Cadangan',
            ],

            'heading' => 'Cadangan',

            'messages' => [
                'backup_success' => 'Membuat cadangan baru di latar belakang.',
                'backup_delete_success' => 'Menghapus cadangan ini di latar belakang.',
            ],

            'modal' => [
                'buttons' => [
                    'only_db' => 'Hanya DB',
                    'only_files' => 'Hanya Berkas',
                    'db_and_files' => 'DB & Berkas',
                ],

                'label' => 'Silakan pilih salah satu opsi',
            ],

            'navigation' => [
                'group' => 'Pengaturan',
                'label' => 'Cadangan',
            ],
        ],
    ],

];
