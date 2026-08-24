<?php

use Filament\Facades\Filament;
use Filament\Panel;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups;

enum TestNavigationIcon: string
{
    case Backups = 'heroicon-o-archive-box';
}

it('configures polling cache queue and backup limits', function () {
    $plugin = FilamentSpatieLaravelBackupPlugin::make()
        ->usingPollingInterval(null)
        ->cacheDuration(90)
        ->backupLimit(15)
        ->usingQueueConnection('redis')
        ->usingQueue('backups');

    expect($plugin)
        ->getPollingInterval()->toBeNull()
        ->getPolingInterval()->toBeNull()
        ->getCacheDuration()->toBe(90)
        ->getBackupLimit()->toBe(15)
        ->getQueueConnection()->toBe('redis')
        ->getQueue()->toBe('backups');
});

it('keeps the deprecated polling interval spelling compatible', function () {
    $plugin = FilamentSpatieLaravelBackupPlugin::make()
        ->usingPolingInterval('45s');

    expect($plugin->getPollingInterval())->toBe('45s');
});

it('keeps cluster configuration isolated between plugin instances', function () {
    $clusteredPlugin = FilamentSpatieLaravelBackupPlugin::make()->cluster('App\\Filament\\Clusters\\System');
    $plainPlugin = FilamentSpatieLaravelBackupPlugin::make();

    expect($clusteredPlugin->getClusterName())->toBe('App\\Filament\\Clusters\\System')
        ->and($plainPlugin->getClusterName())->toBeNull();
});

it('can register its page before a default panel exists', function () {
    $panel = Panel::make()
        ->id('test')
        ->plugin(FilamentSpatieLaravelBackupPlugin::make());

    expect($panel->hasPlugin('filament-spatie-backup'))->toBeTrue();
});

it('resolves cluster configuration from the current panel', function () {
    $clusteredPanel = Panel::make()
        ->id('clustered')
        ->plugin(FilamentSpatieLaravelBackupPlugin::make()->cluster('App\\Filament\\Clusters\\System'));
    $plainPanel = Panel::make()
        ->id('plain')
        ->plugin(FilamentSpatieLaravelBackupPlugin::make());

    Filament::setCurrentPanel($clusteredPanel);
    expect(Backups::getCluster())->toBe('App\\Filament\\Clusters\\System');

    Filament::setCurrentPanel($plainPanel);
    expect(Backups::getCluster())->toBeNull();
});

it('makes no timeout visible to the queue worker', function () {
    $plugin = FilamentSpatieLaravelBackupPlugin::make()->noTimeout();

    expect($plugin->getTimeout())->toBe(0);
});

it('rejects invalid cache durations and backup limits', function () {
    expect(fn () => FilamentSpatieLaravelBackupPlugin::make()->cacheDuration(-1))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => FilamentSpatieLaravelBackupPlugin::make()->backupLimit(0))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => FilamentSpatieLaravelBackupPlugin::make()->timeout(-1))
        ->toThrow(InvalidArgumentException::class);
});

it('configures authorization page and status table visibility', function () {
    $plugin = FilamentSpatieLaravelBackupPlugin::make();

    expect($plugin->isAuthorized())->toBeTrue()
        ->and($plugin->getPage())->toBe(Backups::class)
        ->and($plugin->hasStatusListRecordsTable())->toBeTrue()
        ->and($plugin->getHeading())->toBe('Backups');

    $plugin
        ->authorize(fn (): bool => false)
        ->usingPage('App\\Filament\\Pages\\CustomBackups')
        ->statusListRecordsTable(false);

    expect($plugin->isAuthorized())->toBeFalse()
        ->and($plugin->getPage())->toBe('App\\Filament\\Pages\\CustomBackups')
        ->and($plugin->hasStatusListRecordsTable())->toBeFalse();

    $plugin->boot(Panel::make()->id('test'));
});

it('configures navigation defaults values closures and backed enums', function () {
    $plugin = FilamentSpatieLaravelBackupPlugin::make();

    expect($plugin->getNavigationGroup())->toBe('Settings')
        ->and($plugin->getNavigationSort())->toBe(1)
        ->and($plugin->getNavigationIcon())->toBe('heroicon-o-cog')
        ->and($plugin->getNavigationLabel())->toBe('Backups');

    $plugin
        ->navigationGroup(fn (): string => 'Operations')
        ->navigationSort(fn (): int => 25)
        ->navigationIcon(fn (): TestNavigationIcon => TestNavigationIcon::Backups)
        ->navigationLabel(fn (): string => 'Snapshots');

    expect($plugin->getNavigationGroup())->toBe('Operations')
        ->and($plugin->getNavigationSort())->toBe(25)
        ->and($plugin->getNavigationIcon())->toBe(TestNavigationIcon::Backups->value)
        ->and($plugin->getNavigationLabel())->toBe('Snapshots')
        ->and(FilamentSpatieLaravelBackupPlugin::make()->navigationGroup(null)->getNavigationGroup())->toBeNull();
});
