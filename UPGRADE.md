# Upgrade Guide

## Unreleased changes after v3.4.0

The following compatibility and behavioral changes apply when upgrading from v3.4.0 to the current development version.

### Polling and backup listings

- The default polling interval has increased from 4 seconds to 30 seconds. Use `usingPollingInterval('4s')` to retain the previous interval, or pass `null` to disable polling.
- `getPollingInterval()`, the deprecated `getPolingInterval()`, and the backup table components' `interval()` methods can now return `null`. Custom code calling these methods must handle a nullable value.
- Backup metadata is cached for 30 seconds by default instead of 4 seconds. Use `cacheDuration(4)` to retain the previous duration or `cacheDuration(0)` to disable metadata caching.
- Backup records are listed newest first and paginated with 10 records per page by default. The available page sizes are 10, 25, and 50.
- Selecting a disk filter now queries and displays only that configured destination.
- IDs returned by `getBackupDestinationStatusData()` are now deterministic SHA-1 strings and the records use deterministic ordering. Update integrations or tests that rely on the previous numeric/index IDs or ordering.

### Queue execution and failures

Backup jobs are dispatched immediately rather than after the HTTP response. A `sync` queue connection therefore performs the backup inside the web request, which can increase response time or reach the web request timeout. For production backups, configure an asynchronous connection and run its worker:

```php
FilamentSpatieLaravelBackupPlugin::make()
    ->usingQueueConnection('redis')
    ->usingQueue('backups')
```

A non-zero backup command exit code now throws an exception. Asynchronous workers will retry and eventually record the job as failed according to the application's queue configuration. Review the worker's retry, backoff, timeout, and failed-job settings before upgrading.

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
