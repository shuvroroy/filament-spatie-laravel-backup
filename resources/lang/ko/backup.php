<?php

return [

    'components' => [
        'backup_destination_list' => [
            'table' => [
                'actions' => [
                    'download' => '다운로드',
                    'delete' => '삭제',
                ],

                'fields' => [
                    'path' => '경로',
                    'disk' => '디스크',
                    'date' => '날짜',
                    'size' => '크기',
                ],

                'filters' => [
                    'disk' => '디스크',
                ],
            ],
        ],

        'backup_destination_status_list' => [
            'table' => [
                'fields' => [
                    'name' => '이름',
                    'disk' => '디스크',
                    'healthy' => '상태',
                    'amount' => '개수',
                    'newest' => '최신',
                    'used_storage' => '사용된 저장소',
                ],
            ],
        ],
    ],

    'pages' => [
        'backups' => [
            'actions' => [
                'create_backup' => '백업 생성',
            ],

            'heading' => '백업',

            'messages' => [
                'backup_success' => '새 백업을 백그라운드에서 생성하는 중입니다.',
                'backup_delete_success' => '이 백업을 백그라운드에서 삭제하는 중입니다.',
            ],

            'modal' => [
                'buttons' => [
                    'only_db' => '데이터베이스만',
                    'only_files' => '파일만',
                    'db_and_files' => '데이터베이스 & 파일',
                ],

                'label' => '옵션을 선택해 주세요',
            ],

            'navigation' => [
                'group' => '설정',
                'label' => '백업',
            ],
        ],
    ],

];
