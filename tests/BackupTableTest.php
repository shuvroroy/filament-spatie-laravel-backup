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

it('applies the live backup type filter to custom backup data', function () {
    Storage::disk('backups')->put('test-app/only-db-2026-08-26-00-00-00.zip', 'database');
    Storage::disk('backups')->put('test-app/only-files-2026-08-27-00-00-00.zip', 'files');

    $records = backupTableRecords(
        FilamentSpatieLaravelBackupPlugin::make()->cacheDuration(0),
        filters: ['type' => ['value' => 'only-db']],
    );

    expect($records->total())->toBe(1)
        ->and($records->items()[0]['path'])->toBe('test-app/only-db-2026-08-26-00-00-00.zip')
        ->and($records->items()[0]['type'])->toBe('only-db');
});

it('searches sorts and returns all matching custom records', function () {
    $records = backupTableRecords(
        FilamentSpatieLaravelBackupPlugin::make()->cacheDuration(0),
        page: 0,
        recordsPerPage: 'all',
        filters: ['disk' => ['value' => 'not-configured']],
        sortColumn: 'path',
        sortDirection: 'asc',
        search: 'archive',
    );

    expect($records->currentPage())->toBe(1)
        ->and($records->total())->toBe(5)
        ->and($records->perPage())->toBe(5)
        ->and($records->items()[0]['path'])->toBe('test-app/2026-08-21-00-00-00.zip');
});

function backupTableRecords(
    FilamentSpatieLaravelBackupPlugin $plugin,
    int $page = 1,
    int | string | null $recordsPerPage = 10,
    array $filters = [],
    ?string $sortColumn = null,
    ?string $sortDirection = null,
    ?string $search = null,
): LengthAwarePaginator {
    $panel = Panel::make()
        ->id('test')
        ->plugin($plugin);

    Filament::setCurrentPanel($panel);

    $component = app(BackupDestinationListRecords::class);
    $table = $component->table(Table::make($component));
    $records = $table->getDataSource()(
        $sortColumn,
        $sortDirection,
        $search,
        $filters,
        $page,
        $recordsPerPage,
    );

    expect($records)->toBeInstanceOf(LengthAwarePaginator::class);

    return $records;
}
