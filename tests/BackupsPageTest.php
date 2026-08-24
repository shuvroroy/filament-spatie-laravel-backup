<?php

use Filament\Actions\Action;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Bus;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups;

class TestableBackupsPage extends Backups
{
    /** @return list<Action> */
    public function actionsForTesting(): array
    {
        return array_values(array_filter(
            $this->getHeaderActions(),
            fn (mixed $action): bool => $action instanceof Action,
        ));
    }
}

class BackupsPageTestCluster extends Cluster {}

it('dispatches backup jobs immediately to the configured queue', function () {
    Bus::fake();

    $panel = Panel::make()
        ->default()
        ->id('test')
        ->plugin(
            FilamentSpatieLaravelBackupPlugin::make()
                ->usingQueueConnection('redis')
                ->usingQueue('backups')
                ->timeout(120),
        );

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);

    app(Backups::class)->create('only-db');

    Bus::assertDispatched(
        CreateBackupJob::class,
        fn (CreateBackupJob $job): bool => $job->connection === 'redis'
        && $job->queue === 'backups'
        && $job->timeout === 120
    );
    Bus::assertNotDispatchedAfterResponse(CreateBackupJob::class);
});

it('exposes configured page navigation authorization and actions', function () {
    $plugin = FilamentSpatieLaravelBackupPlugin::make()
        ->authorize(false)
        ->navigationGroup('Operations')
        ->navigationLabel('Snapshots')
        ->navigationSort(20)
        ->navigationIcon('heroicon-o-archive-box')
        ->statusListRecordsTable(false);
    $panel = Panel::make()
        ->default()
        ->id('test')
        ->plugin($plugin);

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);

    $page = app(TestableBackupsPage::class);
    $actions = $page->actionsForTesting();

    expect($page->getHeading())->toBe('Backups')
        ->and(Backups::getNavigationGroup())->toBe('Operations')
        ->and(Backups::getNavigationLabel())->toBe('Snapshots')
        ->and(Backups::getNavigationSort())->toBe(20)
        ->and(Backups::getNavigationIcon())->toBe('heroicon-o-archive-box')
        ->and(Backups::canAccess())->toBeFalse()
        ->and($page->shouldDisplayStatusListRecords())->toBeFalse()
        ->and($actions)->toHaveCount(1)
        ->and($actions[0]->isVisible())->toBeFalse();

    $page->openOptionModal();
});

it('hides the navigation group when the page belongs to a cluster', function () {
    $panel = Panel::make()
        ->default()
        ->id('clustered')
        ->plugin(FilamentSpatieLaravelBackupPlugin::make()->cluster(BackupsPageTestCluster::class));

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);

    expect(Backups::getNavigationGroup())->toBeNull();
});
