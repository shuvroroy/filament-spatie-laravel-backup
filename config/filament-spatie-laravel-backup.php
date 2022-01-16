<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Page
    |--------------------------------------------------------------------------
    |
    | This is the configuration for the general appearance of the page
    | in admin panel.
    |
    | Override is an option to create a new Backup page for customize it,
    | default is false to use Backup Component Page.
    |
    */

    'page' => [
        'override' => false,
        'navigation' => [
            'sort' => null,
        ],
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

];
