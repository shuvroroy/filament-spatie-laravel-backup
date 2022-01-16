<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup;

use Filament\PluginServiceProvider;
use Livewire\Livewire;
use ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationListRecords;
use ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationStatusListRecords;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups;
use Spatie\LaravelPackageTools\Package;

class FilamentSpatieLaravelBackupServiceProvider extends PluginServiceProvider
{
    public static string $name = 'filament-spatie-backup';

    public function configurePackage(Package $package): void
    {
        parent::configurePackage($package);

        $package->hasConfigFile('filament-spatie-laravel-backup');
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

        foreach ([
            BackupDestinationListRecords::class,
            BackupDestinationStatusListRecords::class,
        ] as $component) {
            Livewire::component($component::getName(), $component);
        }
    }

    protected function getPages(): array
    {
        if (config('filament-spatie-laravel-backup.page.override')) {
            return [];
        }

        return [
            Backups::class,
        ];
    }

    protected function getStyles(): array
    {
        return [
            self::$name . '-styles' => __DIR__ . '/../dist/app.css',
        ];
    }
}
