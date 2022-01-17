# Filament Spatie Laravel Backup

[![Latest Version on Packagist](https://img.shields.io/packagist/v/shuvroroy/filament-spatie-laravel-backup.svg?style=flat-square)](https://packagist.org/packages/shuvroroy/filament-spatie-laravel-backup)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/shuvroroy/filament-spatie-laravel-backup/run-tests?label=tests)](https://github.com/shuvroroy/filament-spatie-laravel-backup/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/shuvroroy/filament-spatie-laravel-backup/Check%20&%20fix%20styling?label=code%20style)](https://github.com/shuvroroy/filament-spatie-laravel-backup/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/shuvroroy/filament-spatie-laravel-backup.svg?style=flat-square)](https://packagist.org/packages/shuvroroy/filament-spatie-laravel-backup)

This package provides a Filament page that you can create backup of your application. You'll find installation instructions and full documentation on [spatie/laravel-backup](https://spatie.be/docs/laravel-backup/v7/introduction).

![Backups - Filament Demo](https://user-images.githubusercontent.com/21066418/147877751-faf7f541-3a47-4699-bf5e-2e87990d3bfe.png)


## Installation

You can install the package via composer:

```bash
composer require shuvroroy/filament-spatie-laravel-backup
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="filament-spatie-backup-config"
```

This is the contents of the published config file:

```php
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
    | This is the configuration for the interval seconds between
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
```

## Usage

This package will automatically register the `Backups`. You'll be able to see it when you visit your Filament admin panel.

## Customising the polling interval

You can customise the polling interval for the `Backups` by publishing the configuration file and updating the `polling.interval` value.

## Customising the queue

You can customise the queue name for the `Backups` by publishing the configuration file and updating the `queue` value.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Shuvro Roy](https://github.com/shuvroroy)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
