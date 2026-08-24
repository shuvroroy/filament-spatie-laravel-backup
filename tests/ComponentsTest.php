<?php

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Tables\Table;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationListRecords;
use ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationStatusListRecords;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

beforeEach(function () {
    config()->set('app.timezone', 'UTC');
    config()->set('backup.backup.name', 'test-app');
    config()->set('backup.backup.destination.disks', ['backups']);
    config()->set('backup.monitor_backups', []);

    Storage::fake('backups');

    $panel = Panel::make()
        ->default()
        ->id('test')
        ->plugin(
            FilamentSpatieLaravelBackupPlugin::make()
                ->cacheDuration(0)
                ->usingPollingInterval('45s'),
        );

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);
});

it('renders and configures both backup record components', function () {
    Storage::disk('backups')->put('test-app/2026-08-24-00-00-00.zip', 'backup');
    config()->set('backup.monitor_backups', [[
        'name' => 'test-app',
        'disks' => ['backups'],
        'health_checks' => [],
    ]]);

    $backups = app(BackupDestinationListRecords::class);
    $statuses = app(BackupDestinationStatusListRecords::class);
    $backupTable = $backups->table(Table::make($backups));
    $statusTable = $statuses->table(Table::make($statuses));
    $statusDataSource = $statusTable->getDataSource();

    if ($statusDataSource === null) {
        throw new LogicException('The status table has no data source.');
    }

    $statusRecords = $statusDataSource();

    expect($backups->render()->name())
        ->toBe('filament-spatie-backup::components.backup-destination-list-records')
        ->and($statuses->render()->name())
        ->toBe('filament-spatie-backup::components.backup-destination-status-list-records')
        ->and($backups->interval())->toBe('45s')
        ->and($statuses->interval())->toBe('45s')
        ->and($backupTable->getColumns())->toHaveCount(4)
        ->and(array_keys($backupTable->getFilters()))->toBe(['disk', 'type'])
        ->and($backupTable->hasDeferredFilters())->toBeFalse()
        ->and($statusTable->getColumns())->toHaveCount(6)
        ->and($statusRecords)->toBe(
            FilamentSpatieLaravelBackup::getBackupDestinationStatusData(cacheDuration: 0),
        );
});

it('downloads and deletes a backup through its table actions', function () {
    $path = 'test-app/2026-08-24-00-00-00.zip';
    Storage::disk('backups')->put($path, 'backup');

    $component = app(BackupDestinationListRecords::class);
    $table = $component->table(Table::make($component));
    $download = backupRecordAction($table, 'download');
    $delete = backupRecordAction($table, 'delete');
    $record = FilamentSpatieLaravelBackup::getBackupDestinationData('backups', cacheDuration: 60)[0];

    expect($download->isVisible())->toBeFalse()
        ->and($delete->isVisible())->toBeFalse()
        ->and(runBackupRecordAction($download, $record))->not->toBeNull();

    runBackupRecordAction($delete, $record);

    expect(Storage::disk('backups')->exists($path))->toBeFalse()
        ->and(FilamentSpatieLaravelBackup::getBackupDestinationData('backups', cacheDuration: 60))
        ->toBeEmpty();
});

it('throws when a table action cannot delete a backup', function () {
    $filesystem = Mockery::mock(Filesystem::class);
    $filesystem->shouldReceive('delete')
        ->once()
        ->with('test-app/failed.zip')
        ->andReturnFalse();
    Storage::shouldReceive('disk')->once()->with('backups')->andReturn($filesystem);

    $component = app(BackupDestinationListRecords::class);
    $table = $component->table(Table::make($component));
    $delete = backupRecordAction($table, 'delete');

    runBackupRecordAction($delete, [
        'disk' => 'backups',
        'path' => 'test-app/failed.zip',
    ]);
})->throws(
    RuntimeException::class,
    'The backup [test-app/failed.zip] could not be deleted from disk [backups].',
);

function backupRecordAction(Table $table, string $name): Action
{
    foreach ($table->getRecordActions() as $action) {
        if ($action instanceof Action && $action->getName() === $name) {
            return $action;
        }
    }

    throw new LogicException("The [{$name}] backup action is not configured.");
}

/** @param array<string, string> $record */
function runBackupRecordAction(Action $action, array $record): mixed
{
    $callback = $action->getActionFunction();

    if ($callback === null) {
        throw new LogicException("The [{$action->getName()}] backup action has no callback.");
    }

    return $callback($record);
}
