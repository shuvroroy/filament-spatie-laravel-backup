# Filament Spatie Laravel Backup

[![PHP Version Require](https://poser.pugx.org/shuvroroy/filament-spatie-laravel-backup/require/php)](https://packagist.org/packages/shuvroroy/filament-spatie-laravel-backup)
[![Latest Stable Version](https://poser.pugx.org/shuvroroy/filament-spatie-laravel-backup/v)](https://packagist.org/packages/shuvroroy/filament-spatie-laravel-backup)
[![Total Downloads](https://poser.pugx.org/shuvroroy/filament-spatie-laravel-backup/downloads)](https://packagist.org/packages/shuvroroy/filament-spatie-laravel-backup)
[![License](https://poser.pugx.org/shuvroroy/filament-spatie-laravel-backup/license)](https://packagist.org/packages/shuvroroy/filament-spatie-laravel-backup)

This package adds a Filament page for creating, monitoring, downloading, and deleting application backups. It uses [spatie/laravel-backup](https://spatie.be/docs/laravel-backup/v10/introduction) to perform and monitor the backups.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Permissions](#permissions)
- [Configuration](#configuration)
- [Restoring backups](#restoring-backups)
- [Troubleshooting](#troubleshooting)
- [Upgrading](#upgrading)

## Requirements

Version 4 supports the following stack:

| Dependency | Supported versions |
| --- | --- |
| PHP | 8.2–8.5 |
| Laravel | 12–13 |
| Filament | 4–5 |
| Spatie Laravel Backup | 9–10 |

PHP 8.2 is supported with Laravel 12 and Spatie Laravel Backup 9. Laravel 13 and Spatie Laravel Backup 10 require a newer PHP version.

See the [Laravel support policy](https://laravel.com/docs/12.x/releases#support-policy), [PHP supported versions](https://www.php.net/supported-versions.php), and [Filament support policy](https://filamentphp.com/docs/5.x/introduction/version-support-policy) for upstream support timelines.

Upgrading from v3? Follow the [v4 upgrade guide](UPGRADE.md) before changing the package constraint.

## Installation

You can install the package via composer:

```bash
composer require shuvroroy/filament-spatie-laravel-backup
```

Publish and review Spatie Laravel Backup's configuration. This creates `config/backup.php`, where you define the application name, source files and databases, destination disks, notifications, monitoring, and cleanup strategy:

```bash
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider" --tag="backup-config"
```

Before creating a backup, review these values in `config/backup.php`:

- `backup.name`
- `backup.source.files.include` and `backup.source.databases`
- `backup.destination.disks`

The temporary directory and every destination must be writable and have enough free space for the archive. Database backups also require the relevant dump utility, such as `mysqldump` or `pg_dump`, to be available to both the web process and queue workers. See Spatie's [requirements](https://spatie.be/docs/laravel-backup/v10/requirements) and [installation and setup guide](https://spatie.be/docs/laravel-backup/v10/installation-and-setup) for disk, scheduling, cleanup, monitoring, and database-dump configuration.

Publish the package's assets:

```bash
php artisan filament:assets
```

You can publish the lang file with:

```bash
php artisan vendor:publish --tag="filament-spatie-backup-translations"
```

## Quick start

You first need to register the plugin with Filament. This can be done inside of your `PanelProvider`, e.g. `AdminPanelProvider`.

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

When registering several plugins at once, use `plugins([...])`:

```php
return $panel->plugins([
    FirstPlugin::make(),
    FilamentSpatieLaravelBackupPlugin::make(),
]);
```

Do not pass multiple plugins as arguments to `plugin()`. That method accepts one plugin, so later arguments will not be registered.

The `sync` queue is convenient for local development, but production applications should process backups outside the Livewire request. Configure a supported asynchronous connection and start a worker before using the page:

```dotenv
QUEUE_CONNECTION=database
```

```bash
php artisan queue:work database
```

Complete any setup required by your chosen driver and keep the worker running with a process monitor. See [Laravel's queue documentation](https://laravel.com/docs/queues) and the [queue configuration](#configuring-the-queue) below.

## Permissions

The package checks these Laravel Gate abilities before showing its backup actions:

- `create-backup` — create a new backup.
- `download-backup` — download an existing backup.
- `delete-backup` — delete an existing backup.

Define them with plain Laravel gates in a service provider if you do not use a permissions package. Replace the example condition with your application's authorization rule:

```php
<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('create-backup', fn (User $user): bool => (bool) $user->is_admin);
        Gate::define('download-backup', fn (User $user): bool => (bool) $user->is_admin);
        Gate::define('delete-backup', fn (User $user): bool => (bool) $user->is_admin);
    }
}
```

To restrict access to the entire page, configure the plugin separately:

```php
FilamentSpatieLaravelBackupPlugin::make()
    ->authorize(fn (): bool => auth()->user()?->can('view-backups') ?? false)
```

If you use [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission) or [Filament Shield](https://github.com/bezhansalleh/filament-shield), register the same three action permissions with that package.

### Seeder Example

You can create a seeder to register these permissions and assign them to a role:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BackupPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'download-backup',
            'delete-backup',
            'create-backup',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $role = Role::firstOrCreate(['name' => 'backup']);
        $role->givePermissionTo($permissions);

        $user = \App\Models\User::find(1);

        if ($user && ! $user->hasRole('backup')) {
            $user->assignRole('backup');
        }
    }
}
```

Run the seeder using:

```bash
php artisan db:seed --class=BackupPermissionSeeder
```

## Configuration

### Custom page

Use the plugin methods below to customise navigation. Extend the page only when you need to customise page behaviour, such as its heading:

```php
<?php

namespace App\Filament\Pages;

use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;

class Backups extends BaseBackups
{
    public function getHeading(): string
    {
        return 'Application Backups';
    }
}
```

Then register the extended page class in `AdminPanelProvider`:

```php
<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Backups;
use Filament\Panel;
use Filament\PanelProvider;
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
            );
    }
}
```

### Customising navigation

The default navigation icon is `heroicon-o-archive-box-arrow-down`. You can customise the icon, label, group, and sort order directly on the plugin without extending the page class:

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
            ->plugin(
                FilamentSpatieLaravelBackupPlugin::make()
                    ->navigationIcon('heroicon-o-server-stack')
                    ->navigationLabel('Backups')
                    ->navigationGroup('Settings')
                    ->navigationSort(3)
            );
    }
}
```

All navigation methods also accept closures for dynamic values:

```php
FilamentSpatieLaravelBackupPlugin::make()
    ->navigationLabel(fn (): string => __('custom.backups'))
    ->navigationGroup(fn (): ?string => auth()->user()?->isAdmin() === true ? 'Admin' : 'Tools')
```

> [!NOTE]
> Pass `null` to `navigationGroup()` to remove the page from any navigation group.

### Using the page in a cluster

Pass the cluster class to the plugin. The navigation group is omitted automatically while the page is clustered:

```php
use App\Filament\Clusters\System;

FilamentSpatieLaravelBackupPlugin::make()
    ->cluster(System::class)
```

### Customising the polling interval

You can customise the polling interval for the `Backups` by following the steps below:

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
            ->plugin(
                FilamentSpatieLaravelBackupPlugin::make()
                    ->usingPollingInterval('60s') // default value is 30s
            );
    }
}
```

> [!NOTE]
> Pass `null` to disable polling.

### Large or remote backup destinations

Backup metadata is cached for 30 seconds by default. The table starts at 10 records per page and offers 10, 25, and 50-record page sizes. You can tune the cache, show only the newest backups, or hide the health summary when a remote disk is especially slow.

Backups are listed newest first. Selecting a disk filter queries and displays only that configured destination, avoiding unnecessary reads from the other backup disks.

The Disk and Backup Type filters update the table immediately without an Apply button. Backup type is inferred from the filename prefix: names beginning with `only-db-` are shown as **Only DB**, names beginning with `only-files-` as **Only Files**, and all other names as **DB & Files**. A marker elsewhere in a filename is not treated as a backup type.

```php
FilamentSpatieLaravelBackupPlugin::make()
    ->cacheDuration(60)
    ->backupLimit(15)
    ->statusListRecordsTable(false)
```

Set `cacheDuration(0)` to disable metadata caching, or `backupLimit(null)` to make every backup available to the paginated table.

### Backup type API

Manual backup creation, filename detection, and table filtering use the same enum values:

```php
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\BackupType;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;

BackupType::ONLY_DATABASE->value;      // only-db
BackupType::ONLY_FILES->value;         // only-files
BackupType::DATABASE_AND_FILES->value; // db-and-files

$type = FilamentSpatieLaravelBackup::detectBackupType($backupPath); // BackupType
```

Extensions that mutate backup files can invalidate one known destination with `clearBackupDestinationCache($disk, $backupName)`, or every configured destination with `clearBackupDestinationCaches()`.

### Configuring the queue

By default, backup jobs use your application's default queue connection and queue. You can route them to a dedicated connection and queue:

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
            ->plugin(
                FilamentSpatieLaravelBackupPlugin::make()
                    ->usingQueueConnection('redis')
                    ->usingQueue('backups')
            );
    }
}
```

> **Production queue requirement:** configure a non-`sync` connection and run a queue worker. With Laravel's default `sync` connection, the backup runs inside the Livewire web request and can still exceed the web server or proxy timeout. The job timeout only mitigates this; it does not make a synchronous backup asynchronous.

Failed backup commands fail the queued job, so asynchronous workers can retry them and record them in the configured failed-jobs store.

Start a worker for the same connection and queue. For the example above, run:

```bash
php artisan queue:work redis --queue=backups
```

### Customising the timeout

You can customise the timeout for the backup job by following the steps below:

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
            ->plugin(
                FilamentSpatieLaravelBackupPlugin::make()
                    ->timeout(120)
            );
    }
}
```

The value is applied to Laravel's per-job queue timeout. It is also passed to the backup command and PHP's execution time limit when `set_time_limit()` is available. On hosts where that function is disabled, the package safely leaves PHP's execution limit unchanged. When no value is configured, the PHP and queue-worker defaults apply. For more details, see [`set_time_limit()`](https://www.php.net/manual/en/function.set-time-limit.php).

You can also disable the timeout altogether to let the job run as long as needed:

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
            ->plugin(
                FilamentSpatieLaravelBackupPlugin::make()
                    ->noTimeout()
            );
    }
}
```

Disabling the timeout does not remove the queue worker's memory limit. Size that limit separately for large backups.

### Configuration reference

| Method | Default | Purpose |
| --- | --- | --- |
| `usingPage()` | Package `Backups` page | Register a custom page that extends the package page. |
| `authorize()` | `true` | Control access to the entire backups page. |
| `usingQueueConnection()` | Application default | Choose the connection used by backup jobs. |
| `usingQueue()` | Default queue | Choose the queue used by backup jobs. |
| `usingPollingInterval()` | `'30s'` | Set the Livewire polling interval; pass `null` to disable it. |
| `cacheDuration()` | `30` seconds | Cache destination metadata; use `0` to disable caching. |
| `backupLimit()` | `null` | Limit the table to the newest configured number of backups. |
| `statusListRecordsTable()` | `true` | Show or hide the backup destination health summary. |
| `timeout()` | PHP and worker defaults | Set the backup job timeout in seconds. |
| `noTimeout()` | Not enabled | Set the job timeout to `0`. |
| `cluster()` | `null` | Place the page in a Filament cluster. |
| `navigationIcon()` | `heroicon-o-archive-box-arrow-down` | Set the navigation icon. |
| `navigationLabel()` | Translated `Backups` label | Set the navigation label. |
| `navigationGroup()` | Translated `Settings` group | Set the group; pass `null` to remove it. |
| `navigationSort()` | `1` | Set the navigation sort order. |

`cacheDuration()` and `timeout()` accept zero or greater. `backupLimit()` accepts `null` or an integer of at least one. Navigation values and `authorize()` can also be resolved dynamically with closures where their method signatures allow it.

## Restoring backups

This package creates, lists, downloads, and deletes backups. It intentionally does not restore them: restoring an archive can overwrite live files and databases and requires application-specific validation and recovery steps. Download the archive and follow your application's tested recovery procedure instead.

## Troubleshooting

- **“Plugin is not registered for panel”**: ensure the backup plugin is actually registered. Use separate `plugin()` calls or one `plugins([...])` call, especially when combining it with other plugins.
- **A backup remains pending**: make sure a worker is listening on the connection and queue configured with `usingQueueConnection()` and `usingQueue()`. Check the application log and failed-jobs store for the underlying command error.
- **A manual backup times out in the browser**: the application is probably using the `sync` queue. Select an asynchronous connection and keep its worker running; increasing `timeout()` alone does not move the work outside the Livewire request.
- **`mysqldump`, `pg_dump`, or another dump binary cannot be found**: install the utility on the worker host or configure its directory with `dump.dump_binary_path` on the relevant connection in `config/database.php`.
- **The archive cannot be written or a destination is unreachable**: verify temporary-directory and destination permissions, available disk space, remote credentials, and network access from the queue-worker host.
- **Configuration changes are ignored**: clear or rebuild Laravel's configuration cache after changing `config/backup.php`, `config/filesystems.php`, queue settings, or environment variables. Restart long-running queue workers after deployment so they load the new configuration.
- **“Could not find driver” while an old v2 release creates `backup_destination_*` tables**: upgrade to v3. The current implementation uses Filament custom data and does not require SQLite or Sushi models.
- **Slow S3-compatible disks**: increase `cacheDuration()`, increase or disable polling, set `backupLimit()`, or hide the status table as shown above.

## Upgrading

Existing v3 applications should review the [v4 upgrade guide](UPGRADE.md) before updating. It covers the new polling and cache defaults, paginated backup listings, queue execution timing, and failed-job behavior. The same guide also contains instructions for earlier major versions.

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
