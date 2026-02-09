<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationListRecords;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

beforeEach(function () {
    config()->set('backup.backup', [
        'name' => 'test-backup',
        'destination' => ['disks' => ['local']],
    ]);

    Storage::fake('local');

    // Backup rows are cached for 4 seconds by disk key
    // @see ShuvroRoy\FilamentSpatieLaravelBackup.php:40
    Cache::forget('backups-local');

    Auth::setUser(new class extends Authenticatable {});

    Filament::getCurrentOrDefaultPanel()->plugins([
        FilamentSpatieLaravelBackupPlugin::make(),
    ]);
});

it('paginates backups by 10 by default', function () {
    Livewire::test(BackupDestinationListRecords::class)
        ->assertCountTableRecords(0);

    $backupName = config('backup.backup.name');
    $disk = Storage::disk('local');

    collect(range(1, 15))->each(function (int $i) use ($disk, $backupName) {
        $disk->put(
            "{$backupName}/2026-02-01-12-" . sprintf('%02d', $i) . '-00.zip',
            "backup-{$i}",
        );
    });

    Cache::forget('backups-local');

    Livewire::test(BackupDestinationListRecords::class)
        ->assertCountTableRecords(15)
        ->assertSee('2026-02-01-12-06-00.zip')
        ->assertDontSee('2026-02-01-12-05-00.zip')
        ->call('gotoPage', 2)
        ->assertSee('2026-02-01-12-05-00.zip')
        ->assertDontSee('2026-02-01-12-06-00.zip');
});
