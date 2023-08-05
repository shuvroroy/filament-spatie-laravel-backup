<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Tests;

use Filament\FilamentServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'ShuvroRoy\\FilamentSpatieLaravelBackup\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

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
