# Filament Spatie Laravel Backup

[![PHP Version Require](https://poser.pugx.org/shuvroroy/filament-spatie-laravel-backup/require/php)](https://packagist.org/packages/shuvroroy/filament-spatie-laravel-backup)
[![Latest Stable Version](https://poser.pugx.org/shuvroroy/filament-spatie-laravel-backup/v)](https://packagist.org/packages/shuvroroy/filament-spatie-laravel-backup)
[![Total Downloads](https://poser.pugx.org/shuvroroy/filament-spatie-laravel-backup/downloads)](https://packagist.org/packages/shuvroroy/filament-spatie-laravel-backup)
[![License](https://poser.pugx.org/shuvroroy/filament-spatie-laravel-backup/license)](https://packagist.org/packages/shuvroroy/filament-spatie-laravel-backup)

This package adds a Filament page for creating, monitoring, downloading, and deleting application backups. It uses [spatie/laravel-backup](https://spatie.be/docs/laravel-backup/v10/introduction) to perform and monitor the backups.

<img width="1481" alt="Screenshot 2023-08-05 at 2 42 10 PM" src="https://github.com/shuvroroy/filament-spatie-laravel-backup/assets/21066418/68fe1c0b-a130-41ce-8c7f-e5182d743225">

## Requirements

Version 4 supports the following stack:

| Dependency | Supported versions |
| --- | --- |
| PHP | 8.3–8.5 |
| Laravel | 12–13 |
| Filament | 4–5 |
| Spatie Laravel Backup | 9–10 |

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

Publish the package's assets:

```bash
php artisan filament:assets
```

You can publish the lang file with:

```bash
php artisan vendor:publish --tag="filament-spatie-backup-translations"
```

## Usage

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

Use the plugin's navigation methods below to customise the icon, label, group, or sort order. Extend the page only when you need to customise page behaviour, such as its heading:

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
            );
    }
}
```

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

## Customising navigation

You can customise the navigation icon, label, group, and sort order directly on the plugin without extending the page class:

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
                    ->navigationIcon('heroicon-o-cpu-chip')
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
    ->navigationGroup(fn (): ?string => auth()->user()->isAdmin() ? 'Admin' : 'Tools')
```

Pass `null` to `navigationGroup()` to remove the page from any navigation group.

## Using the page in a cluster

Pass the cluster class to the plugin. The navigation group is omitted automatically while the page is clustered:

```php
use App\Filament\Clusters\System;

FilamentSpatieLaravelBackupPlugin::make()
    ->cluster(System::class)
```

## Customising the polling interval

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

Pass `null` to disable polling.

## Large or remote backup destinations

Backup metadata is cached for 30 seconds by default and the table is paginated. You can tune the cache, show only the newest backups, or hide the health summary when a remote disk is especially slow.

Backups are listed newest first. Selecting a disk filter queries and displays only that configured destination, avoiding unnecessary reads from the other backup disks.

The Disk and Backup Type filters update the table immediately without an Apply button. Backup type is inferred from the filename prefix: names beginning with `only-db-` are shown as **Only DB**, names beginning with `only-files-` as **Only Files**, and all other names as **DB & Files**. A marker elsewhere in a filename is not treated as a backup type.

```php
FilamentSpatieLaravelBackupPlugin::make()
    ->cacheDuration(60)
    ->backupLimit(15)
    ->statusListRecordsTable(false)
```

Set `cacheDuration(0)` to disable metadata caching, or `backupLimit(null)` to display every backup.

## Backup type API

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

## Customising the queue

You can customise the queue name for the `Backups` by following the steps below:

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

## Customising the timeout

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

## Customising who can access the page

You can customise who can access the `Backups` page by adding an `authorize` method to the plugin.
The method should return a boolean indicating whether the user is authorised to access the page.

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
                     ->authorize(fn (): bool => auth()->user()->email === 'admin@example.com'),
            );
    }
}
```

## Restoring backups

This package creates, lists, downloads, and deletes backups. It intentionally does not restore them: restoring an archive can overwrite live files and databases and requires application-specific validation and recovery steps. Download the archive and follow your application's tested recovery procedure instead.

## Troubleshooting

- **“Plugin is not registered for panel”**: ensure the backup plugin is actually registered. Use separate `plugin()` calls or one `plugins([...])` call, especially when combining it with other plugins.
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
