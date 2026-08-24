<?php

use Carbon\Carbon;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

beforeEach(function () {
    config()->set('app.timezone', 'UTC');
    config()->set('backup.backup.name', 'test-app');
    config()->set('backup.backup.destination.disks', ['backups']);
    config()->set('backup.monitor_backups', []);

    Cache::flush();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('uses directory listing metadata instead of one request per backup', function () {
    $driver = Mockery::mock(FilesystemOperator::class);
    $filesystem = Mockery::mock(FilesystemAdapter::class);
    $listing = new DirectoryListing([
        new FileAttributes('test-app/2026-08-23-00-00-00.zip', 100, lastModified: 1_777_075_200),
        new FileAttributes('test-app/2026-08-24-00-00-00.zip', 200, lastModified: 1_777_161_600),
        new FileAttributes('test-app/readme.txt', 50, lastModified: 1_777_161_600),
    ]);

    Storage::shouldReceive('disk')->once()->with('remote')->andReturn($filesystem);
    $filesystem->shouldReceive('getDriver')->once()->andReturn($driver);
    $filesystem->shouldNotReceive('exists');
    $filesystem->shouldNotReceive('size');
    $filesystem->shouldNotReceive('lastModified');
    $driver->shouldReceive('listContents')->once()->with('test-app', true)->andReturn($listing);

    $records = FilamentSpatieLaravelBackup::getBackupDestinationData('remote', cacheDuration: 0);

    expect($records)->toHaveCount(2)
        ->and($records[0]['path'])->toBe('test-app/2026-08-24-00-00-00.zip')
        ->and($records[0]['size'])->toBe('200 B')
        ->and($records[1]['path'])->toBe('test-app/2026-08-23-00-00-00.zip');
});

it('formats backup dates in the application timezone', function () {
    config()->set('app.timezone', 'Asia/Dhaka');
    Storage::fake('backups');
    Storage::disk('backups')->put('test-app/2026-08-24-00-00-00.zip', 'backup');

    $records = FilamentSpatieLaravelBackup::getBackupDestinationData('backups', cacheDuration: 0);

    expect($records[0]['date'])->toBe('2026-08-24 06:00:00');
});

it('shares and invalidates cached backup snapshots', function () {
    Storage::fake('backups');
    Storage::disk('backups')->put('test-app/2026-08-23-00-00-00.zip', 'old');

    expect(FilamentSpatieLaravelBackup::getBackupDestinationData('backups', cacheDuration: 60))
        ->toHaveCount(1);

    Storage::disk('backups')->put('test-app/2026-08-24-00-00-00.zip', 'new');

    expect(FilamentSpatieLaravelBackup::getBackupDestinationData('backups', cacheDuration: 60))
        ->toHaveCount(1);

    FilamentSpatieLaravelBackup::clearCachedBackupDestinationData('backups', 'test-app');

    expect(FilamentSpatieLaravelBackup::getBackupDestinationData('backups', cacheDuration: 60))
        ->toHaveCount(2);
});

it('builds destination status without a database or sushi models', function () {
    Storage::fake('backups');
    Storage::disk('backups')->put('test-app/2026-08-24-00-00-00.zip', str_repeat('x', 1_024));
    config()->set('backup.monitor_backups', [[
        'name' => 'test-app',
        'disks' => ['backups'],
        'health_checks' => [],
    ]]);

    $statuses = FilamentSpatieLaravelBackup::getBackupDestinationStatusData(cacheDuration: 0);

    expect($statuses)->toHaveCount(1)
        ->and($statuses[0]['reachable'])->toBeTrue()
        ->and($statuses[0]['healthy'])->toBeTrue()
        ->and($statuses[0]['amount'])->toBe(1)
        ->and($statuses[0]['usedStorage'])->toBe('1024 B');
});

it('runs configured health checks against cached metadata', function () {
    Carbon::setTestNow('2026-08-24 12:00:00');
    Storage::fake('backups');
    Storage::disk('backups')->put('test-app/2026-08-24-00-00-00.zip', str_repeat('x', 1_024));
    config()->set('backup.monitor_backups', [[
        'name' => 'test-app',
        'disks' => ['backups'],
        'health_checks' => [
            MaximumAgeInDays::class => 1,
            MaximumStorageInMegabytes::class => 1,
        ],
    ]]);

    $statuses = FilamentSpatieLaravelBackup::getBackupDestinationStatusData(cacheDuration: 0);

    expect($statuses[0]['healthy'])->toBeTrue();
});
