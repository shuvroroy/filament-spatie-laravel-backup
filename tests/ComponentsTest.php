<?php

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Table;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationListRecords;
use ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationStatusListRecords;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

use function PHPUnit\Framework\assertStringContainsString;
use function PHPUnit\Framework\assertStringNotContainsString;

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

    Route::name($panel->generateRouteName(''))->group(function () use ($panel): void {
        foreach ($panel->getAuthenticatedTenantRoutes() as $routes) {
            $routes($panel);
        }
    });

    Route::getRoutes()->refreshNameLookups();
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

it('links to a signed download outside Livewire and deletes a backup through its table action', function () {
    $path = 'test-app/2026-08-24-00-00-00.zip';
    Storage::disk('backups')->put($path, 'backup');

    $component = app(BackupDestinationListRecords::class);
    $table = $component->table(Table::make($component));
    $download = backupRecordAction($table, 'download');
    $delete = backupRecordAction($table, 'delete');
    $record = FilamentSpatieLaravelBackup::getBackupDestinationData('backups', cacheDuration: 60)[0];
    $record['__key'] = $record['key'];
    $download->record($record);
    $url = $download->getUrl();

    if ($url === null) {
        throw new LogicException('The backup download action has no URL.');
    }

    $request = Request::create($url);

    expect($download->isVisible())->toBeFalse()
        ->and($delete->isVisible())->toBeFalse()
        ->and($download->getActionFunction())->toBeNull()
        ->and($download->shouldOpenUrlInNewTab())->toBeTrue()
        ->and(URL::hasValidSignature($request))->toBeTrue()
        ->and($request->query('disk'))->toBe('backups')
        ->and($request->query('path'))->toBe($path);

    runBackupRecordAction($delete, $record);

    expect(Storage::disk('backups')->exists($path))->toBeFalse()
        ->and(FilamentSpatieLaravelBackup::getBackupDestinationData('backups', cacheDuration: 60))
        ->toBeEmpty();
});

it('renders download links without Livewire navigation even in SPA mode', function () {
    FilamentView::spa();

    $component = app(BackupDestinationListRecords::class);
    $download = backupRecordAction($component->table(Table::make($component)), 'download');
    $download->visible(true)->record([
        '__key' => 'backup',
        'disk' => 'backups',
        'path' => 'test-app/backup.zip',
    ]);

    $html = $download->toHtml();

    assertStringContainsString('target="_blank"', $html);
    assertStringNotContainsString('wire:navigate', $html);
    assertStringNotContainsString('wire:click', $html);
});

it('requires a panel when generating a download URL', function () {
    $component = app(BackupDestinationListRecords::class);
    $download = backupRecordAction($component->table(Table::make($component)), 'download');
    $download->record(['disk' => 'backups', 'path' => 'test-app/backup.zip']);

    Filament::shouldReceive('getCurrentOrDefaultPanel')->once()->andReturnNull();

    expect(fn () => $download->getUrl())->toThrow(
        LogicException::class,
        'A Filament panel is required to download a backup.',
    );
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
