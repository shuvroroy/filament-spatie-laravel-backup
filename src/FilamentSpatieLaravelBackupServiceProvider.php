<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup;

use Livewire\Livewire;
use ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationListRecords;
use ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationStatusListRecords;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentSpatieLaravelBackupServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-spatie-backup')
            ->hasTranslations()
            ->hasViews();
    }

    public function packageBooted(): void
    {
        Livewire::component('backup-destination-list-records', BackupDestinationListRecords::class);
        Livewire::component('backup-destination-status-list-records', BackupDestinationStatusListRecords::class);
    }
}
