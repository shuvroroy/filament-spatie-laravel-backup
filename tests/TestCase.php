<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Tests;

use Filament\FilamentServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            FilamentSpatieLaravelBackupServiceProvider::class,
            LivewireServiceProvider::class,
            FilamentServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }
}
