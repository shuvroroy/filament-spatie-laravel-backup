<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | This is the configuration for the general appearance of the page
    | in admin panel.
    |
    */

    'pages' => [
        'backups' => \ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups::class
    ],

    /*
    |--------------------------------------------------------------------------
    | Polling
    |--------------------------------------------------------------------------
    |
    | This is the configuration for the interval between
    | polling requests.
    |
    */

    'polling' => [
        'interval' => '4s'
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Queue to use for the jobs to run through.
    |
    */

    'queue' => null,

    /*
    |--------------------------------------------------------------------------
    | Permission
    |--------------------------------------------------------------------------
    |
    | This is the configuration for backup access permission, leaving null all have access or enter a permission.
    |
    */

    'permission' => null,

];
