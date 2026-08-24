<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationListRecords;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

beforeEach(function () {
    config()->set('app.timezone', 'UTC');
    config()->set('backup.backup.name', 'test-app');
    config()->set('backup.backup.destination.disks', ['backups', 'archive']);
    config()->set('backup.monitor_backups', []);

    Storage::fake('backups');
    Storage::fake('archive');

    foreach (range(1, 20) as $day) {
        Storage::disk('backups')->put(sprintf('test-app/2026-08-%02d-00-00-00.zip', $day), 'backup');
    }

    foreach (range(21, 25) as $day) {
        Storage::disk('archive')->put(sprintf('test-app/2026-08-%02d-00-00-00.zip', $day), 'backup');
    }
});

it('paginates custom backup records', function () {
    $records = backupTableRecords(
        FilamentSpatieLaravelBackupPlugin::make()->cacheDuration(0),
        page: 2,
        recordsPerPage: 10,
    );

    expect($records)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($records->total())->toBe(25)
        ->and($records->count())->toBe(10);
});

it('limits the table to the newest configured number of backups', function () {
    $records = backupTableRecords(
        FilamentSpatieLaravelBackupPlugin::make()
            ->cacheDuration(0)
            ->backupLimit(15),
    );

    expect($records->total())->toBe(15)
        ->and($records->items()[0]['path'])->toBe('test-app/2026-08-25-00-00-00.zip');
});

it('applies the disk filter to custom backup data', function () {
    $records = backupTableRecords(
        FilamentSpatieLaravelBackupPlugin::make()->cacheDuration(0),
        filters: ['disk' => ['value' => 'archive']],
    );

    expect($records->total())->toBe(5)
        ->and(collect($records->items())->pluck('disk')->unique()->all())->toBe(['archive']);
});

function backupTableRecords(
    FilamentSpatieLaravelBackupPlugin $plugin,
    int $page = 1,
    int $recordsPerPage = 10,
    array $filters = [],
): LengthAwarePaginator {
    $panel = Panel::make()
        ->id('test')
        ->plugin($plugin);

    Filament::setCurrentPanel($panel);

    $component = app(BackupDestinationListRecords::class);
    $table = $component->table(Table::make($component));
    $records = $table->getDataSource()(
        null,
        null,
        null,
        $filters,
        $page,
        $recordsPerPage,
    );

    expect($records)->toBeInstanceOf(LengthAwarePaginator::class);

    return $records;
}
