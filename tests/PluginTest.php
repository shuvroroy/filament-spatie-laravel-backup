<?php

use Filament\Clusters\Cluster;
use Filament\Contracts\Plugin as FilamentPlugin;
use Filament\Facades\Filament;
use Filament\Panel;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups;

enum TestNavigationIcon: string
{
    case Backups = 'heroicon-o-archive-box';
}

enum TestIntegerNavigationIcon: int
{
    case Backups = 1;
}

class PluginTestCluster extends Cluster {}

class CustomBackupsPage extends Backups {}

class ConflictingBackupPlugin implements FilamentPlugin
{
    public function getId(): string
    {
        return 'filament-spatie-backup';
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}
}

it('configures polling cache queue and backup limits', function () {
    $plugin = FilamentSpatieLaravelBackupPlugin::make()
        ->usingPollingInterval(null)
        ->cacheDuration(90)
        ->backupLimit(15)
        ->usingQueueConnection('redis')
        ->usingQueue('backups');

    expect($plugin->getPollingInterval())->toBeNull()
        ->and($plugin->getCacheDuration())->toBe(90)
        ->and($plugin->getBackupLimit())->toBe(15)
        ->and($plugin->getQueueConnection())->toBe('redis')
        ->and($plugin->getQueue())->toBe('backups');
});

it('does not expose the removed polling interval aliases', function () {
    $plugin = FilamentSpatieLaravelBackupPlugin::make();

    expect(method_exists($plugin, 'usingPolingInterval'))->toBeFalse()
        ->and(method_exists($plugin, 'getPolingInterval'))->toBeFalse();
});

it('keeps cluster configuration isolated between plugin instances', function () {
    $clusteredPlugin = FilamentSpatieLaravelBackupPlugin::make()->cluster(PluginTestCluster::class);
    $plainPlugin = FilamentSpatieLaravelBackupPlugin::make();

    expect($clusteredPlugin->getClusterName())->toBe(PluginTestCluster::class)
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
        ->plugin(FilamentSpatieLaravelBackupPlugin::make()->cluster(PluginTestCluster::class));
    $plainPanel = Panel::make()
        ->id('plain')
        ->plugin(FilamentSpatieLaravelBackupPlugin::make());

    Filament::setCurrentPanel($clusteredPanel);
    expect(Backups::getCluster())->toBe(PluginTestCluster::class);

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

it('rejects invalid resolved navigation values', function () {
    expect(fn () => FilamentSpatieLaravelBackupPlugin::make()
        ->navigationGroup(fn (): int => 1)
        ->getNavigationGroup())->toThrow(UnexpectedValueException::class)
        ->and(fn () => FilamentSpatieLaravelBackupPlugin::make()
            ->navigationSort(fn (): string => 'first')
            ->getNavigationSort())->toThrow(UnexpectedValueException::class)
        ->and(fn () => FilamentSpatieLaravelBackupPlugin::make()
            ->navigationIcon(TestIntegerNavigationIcon::Backups)
            ->getNavigationIcon())->toThrow(UnexpectedValueException::class)
        ->and(fn () => FilamentSpatieLaravelBackupPlugin::make()
            ->navigationLabel(fn (): int => 1)
            ->getNavigationLabel())->toThrow(UnexpectedValueException::class);
});

it('rejects invalid container and panel plugin resolutions', function () {
    app()->instance(FilamentSpatieLaravelBackupPlugin::class, new stdClass);

    try {
        expect(fn () => FilamentSpatieLaravelBackupPlugin::make())
            ->toThrow(LogicException::class);
    } finally {
        app()->forgetInstance(FilamentSpatieLaravelBackupPlugin::class);
    }

    $panel = Panel::make()
        ->id('conflicting')
        ->plugin(new ConflictingBackupPlugin);

    Filament::setCurrentPanel($panel);

    expect(fn () => FilamentSpatieLaravelBackupPlugin::get())
        ->toThrow(LogicException::class);
});

it('configures authorization page and status table visibility', function () {
    $plugin = FilamentSpatieLaravelBackupPlugin::make();

    expect($plugin->isAuthorized())->toBeTrue()
        ->and($plugin->getPage())->toBe(Backups::class)
        ->and($plugin->hasStatusListRecordsTable())->toBeTrue()
        ->and($plugin->getHeading())->toBe('Backups');

    $plugin
        ->authorize(fn (): bool => false)
        ->usingPage(CustomBackupsPage::class)
        ->statusListRecordsTable(false);

    expect($plugin->isAuthorized())->toBeFalse()
        ->and($plugin->getPage())->toBe(CustomBackupsPage::class)
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
        ->and(FilamentSpatieLaravelBackupPlugin::make()->navigationGroup(null)->getNavigationGroup())->toBeNull()
        ->and(FilamentSpatieLaravelBackupPlugin::make()->navigationIcon(fn (): ?string => null)->getNavigationIcon())->toBeNull();
});
