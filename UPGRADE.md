# Upgrade Guide

## Upgrading from v3.x to v4.0

Update the package constraint and its dependencies:

```sh
composer require shuvroroy/filament-spatie-laravel-backup:^4.0 --with-all-dependencies
```

Then review the following breaking and behavioural changes before deploying v4.0.

### Supported versions

Version 4 supports PHP 8.3–8.5, Laravel 12–13, Filament 4–5, and Spatie Laravel Backup 9–10. Upgrade unsupported framework and runtime versions first. If you have not published Spatie's configuration, publish it now:

```sh
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider" --tag="backup-config"
```

Existing `config/backup.php` files should be compared with the version shipped by the installed Spatie package. Monitor definitions must use the `health_checks` key.

### Removed and renamed APIs

The deprecated, misspelled polling methods have been removed:

| Removed | Replacement |
| --- | --- |
| `usingPolingInterval()` | `usingPollingInterval()` |
| `getPolingInterval()` | `getPollingInterval()` |

The generic `Option` enum has been replaced by `BackupType`:

| v3 | v4 |
| --- | --- |
| `Option::ONLY_DB` (`only-db`) | `BackupType::ONLY_DATABASE` (`only-db`) |
| `Option::ONLY_FILES` (`only-files`) | `BackupType::ONLY_FILES` (`only-files`) |
| `Option::ALL` (empty string) | `BackupType::DATABASE_AND_FILES` (`db-and-files`) |

Update imports to `ShuvroRoy\FilamentSpatieLaravelBackup\Enums\BackupType`. Custom calls to `Backups::create('')` must pass `BackupType::DATABASE_AND_FILES->value` or `db-and-files`. `FilamentSpatieLaravelBackup::detectBackupType()` now returns a `BackupType` enum instead of a string; use its `value` property when a string is required.

The request-coupled `FilamentSpatieLaravelBackup::getDisk()` method has been removed. Read the selected disk from your own component state, or use `getDisks()` for the configured destination list.

The cache-clearing API no longer accepts optional arguments:

| Removed | Replacement |
| --- | --- |
| `clearCachedBackupDestinationData($disk, $name)` | `clearBackupDestinationCache($disk, $name)` |
| `clearCachedBackupDestinationData()` | `clearBackupDestinationCaches()` |

Both arguments are required when clearing one destination, so an accidental partial call can no longer clear every destination.

The package page now uses Filament's `getHeaderActions()` hook. If a custom `Backups` subclass overrides the deprecated `getActions()` hook, rename that override to `getHeaderActions()`.

### Polling and backup listings

- The default polling interval has increased from 4 seconds to 30 seconds. Use `usingPollingInterval('4s')` to retain the previous interval, or pass `null` to disable polling.
- `getPollingInterval()` and the backup table components' `interval()` methods can return `null`. Custom code calling these methods must handle a nullable value.
- Backup metadata is cached for 30 seconds by default instead of 4 seconds. Use `cacheDuration(4)` to retain the previous duration or `cacheDuration(0)` to disable metadata caching.
- Backup records are listed newest first and paginated with 10 records per page by default. The available page sizes are 10, 25, and 50.
- Selecting a disk filter now queries and displays only that configured destination.
- Disk and Backup Type filters now apply immediately. Backup types are inferred only from the `only-db-` and `only-files-` filename prefixes. Markers elsewhere in a filename are ignored, and unmarked filenames are treated as database-and-files backups.
- IDs returned by `getBackupDestinationStatusData()` are now deterministic SHA-1 strings and the records use deterministic ordering. Update integrations or tests that rely on the previous numeric/index IDs or ordering.

### Queue execution and failures

Backup jobs are dispatched immediately rather than after the HTTP response. A `sync` queue connection therefore performs the backup inside the Livewire request and can exceed the web server or proxy timeout.

**Production deployments should configure an asynchronous connection and run its worker before enabling manual backups:**

```php
FilamentSpatieLaravelBackupPlugin::make()
    ->usingQueueConnection('redis')
    ->usingQueue('backups')
```

A non-zero backup command exit code now throws an exception. Asynchronous workers will retry and eventually record the job as failed according to the application's queue configuration. Review the worker's retry, backoff, timeout, and failed-job settings before upgrading.

Configured timeouts continue to set Laravel's per-job timeout. The package now calls `set_time_limit()` and passes the backup command timeout only when that PHP function is available, so hosts that disable it no longer fail before a backup starts.

## Upgrading from v1.x to v2.0

Starting with version v2.0, this package now only supports Filament v3.x.

Follow these steps to update the package for Filament v3.x.

1. Update the package version in your `composer.json`.
2. Run `composer update`.
3. Register the plugin inside of your project's `PanelProvider`, e.g. `AdminPanelProvider`.

```php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ...
            ->plugin(FilamentSpatieLaravelBackupPlugin::make());
    }
}
```

4. Publish the plugin assets.

```sh
php artisan filament:assets
```

5. If you previously used the configuration file to change the `backups`, `interval` & `queue`  value, those no longer exist and need to be updated to method calls on the plugin object.

```php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use App\Filament\Pages\Backups;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ...
            ->plugin(
                FilamentSpatieLaravelBackupPlugin::make()
                    ->usingPage(Backups::class)
                    ->usingQueue('my-queue')
                    ->usingPollingInterval('10s') // default value is 30s
                    ->statusListRecordsTable(false) // default value is true
            );
    }
}
```

If you have any issues with the upgrade, please open an issue and provide details. Reproduction repositories are much appreciated.
