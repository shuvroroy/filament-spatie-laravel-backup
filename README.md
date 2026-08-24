# Filament Spatie Laravel Backup

[![PHP Version Require](https://poser.pugx.org/shuvroroy/filament-spatie-laravel-backup/require/php)](https://packagist.org/packages/shuvroroy/filament-spatie-laravel-backup)
[![Latest Stable Version](https://poser.pugx.org/shuvroroy/filament-spatie-laravel-backup/v)](https://packagist.org/packages/shuvroroy/filament-spatie-laravel-backup)
[![Total Downloads](https://poser.pugx.org/shuvroroy/filament-spatie-laravel-backup/downloads)](https://packagist.org/packages/shuvroroy/filament-spatie-laravel-backup)
[![License](https://poser.pugx.org/shuvroroy/filament-spatie-laravel-backup/license)](https://packagist.org/packages/shuvroroy/filament-spatie-laravel-backup)

This package provides a Filament page that you can create backup of your application. You'll find installation instructions and full documentation on [spatie/laravel-backup](https://spatie.be/docs/laravel-backup/v8/introduction).

<img width="1481" alt="Screenshot 2023-08-05 at 2 42 10 PM" src="https://github.com/shuvroroy/filament-spatie-laravel-backup/assets/21066418/68fe1c0b-a130-41ce-8c7f-e5182d743225">

## Installation

You can install the package via composer:

```bash
composer require shuvroroy/filament-spatie-laravel-backup
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

If you want to override the default `Backups` page icon, heading then you can extend the page class and override the `navigationIcon` property and `getHeading` method and so on.

```php
<?php

namespace App\Filament\Pages;

use Illuminate\Contracts\Support\Htmlable;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;

class Backups extends BaseBackups
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cpu-chip';

    public function getHeading(): string | Htmlable
    {
        return 'Application Backups';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Core';
    }
}
```
Then register the extended page class on `AdminPanelProvider` class.

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

## Permissions Setup (for Creating, Downloading & Deleting backups)

If you're using [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission) or [Filament Shield](https://github.com/bezhansalleh/filament-shield), you need to manually define the permissions used by this backup panel.

### Required Permissions

- `download-backup` – Allows downloading existing backups.
- `delete-backup` – Allows deleting backups from the panel.
- `create-backup` – Allows creating new backups from the panel.

### Seeder Example

You can create a seeder to register these permissions and assign them to a role:

```php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BackupPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions
        $permissions = [
            'download-backup',
            'delete-backup',
            'create-backup',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign to a role (optional)
        $role = Role::firstOrCreate(['name' => 'backup']);
        $role->givePermissionTo($permissions);

        // Assign role to a user (optional)
        $user = \App\Models\User::find(1); // Change ID as needed
        
        if ($user && !$user->hasRole('backup')) {
            $user->assignRole('backup');
        }
    }
}
```

Run the seeder using:

```bash
php artisan db:seed --class=BackupPermissionSeeder
```

After this, users with the `backup` role will have full access to the backup panel.


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

Pass `null` to disable polling. The older `usingPolingInterval()` spelling remains available for backwards compatibility.

## Large or remote backup destinations

Backup metadata is cached for 30 seconds by default and the table is paginated. You can tune the cache, show only the newest backups, or hide the health summary when a remote disk is especially slow:

Backups are listed newest first. Selecting a disk filter queries and displays only that configured destination, avoiding unnecessary reads from the other backup disks.

```php
FilamentSpatieLaravelBackupPlugin::make()
    ->cacheDuration(60)
    ->backupLimit(15)
    ->statusListRecordsTable(false)
```

Set `cacheDuration(0)` to disable metadata caching, or `backupLimit(null)` to display every backup.

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

Use a non-`sync` queue connection and run a worker for production backups. A `sync` connection still performs the backup inside the web request. Failed backup commands now fail the queued job, so they appear in the configured failed-jobs store and worker logs.

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

The value is applied to both PHP's execution time limit and Laravel's per-job queue timeout. When no value is configured, the PHP and queue-worker defaults apply. For more details refer to the [set_time_limit](https://www.php.net/manual/en/function.set-time-limit.php) function.

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

Please see [UPGRADE](UPGRADE.md) for details on how to upgrade 1.X to 2.0.

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
