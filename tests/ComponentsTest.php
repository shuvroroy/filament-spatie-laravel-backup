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
    $statusRecords = $statusTable->getDataSource()();

    expect($backups->render()->name())
        ->toBe('filament-spatie-backup::components.backup-destination-list-records')
        ->and($statuses->render()->name())
        ->toBe('filament-spatie-backup::components.backup-destination-status-list-records')
        ->and($backups->interval())->toBe('45s')
        ->and($statuses->interval())->toBe('45s')
        ->and($backupTable->getColumns())->toHaveCount(4)
        ->and($statusTable->getColumns())->toHaveCount(6)
        ->and($statusRecords)->toHaveCount(1)
        ->and($statusRecords[0]['amount'])->toBe(1);
});

it('downloads and deletes a backup through its table actions', function () {
    $path = 'test-app/2026-08-24-00-00-00.zip';
    Storage::disk('backups')->put($path, 'backup');

    $component = app(BackupDestinationListRecords::class);
    $table = $component->table(Table::make($component));
    $actions = collect($table->getRecordActions())
        ->filter(fn (Action $action): bool => in_array($action->getName(), ['download', 'delete'], true))
        ->keyBy(fn (Action $action): string => $action->getName());
    $record = FilamentSpatieLaravelBackup::getBackupDestinationData('backups', cacheDuration: 60)[0];

    expect($actions['download']->isVisible())->toBeFalse()
        ->and($actions['delete']->isVisible())->toBeFalse()
        ->and(($actions['download']->getActionFunction())($record))->not->toBeNull();

    ($actions['delete']->getActionFunction())($record);

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
    $delete = collect($table->getRecordActions())
        ->first(fn (Action $action): bool => $action->getName() === 'delete');

    ($delete->getActionFunction())([
        'disk' => 'backups',
        'path' => 'test-app/failed.zip',
    ]);
})->throws(RuntimeException::class, 'The backup could not be deleted.');
