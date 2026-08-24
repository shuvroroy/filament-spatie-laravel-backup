<?php

use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\BackupType;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use ShuvroRoy\FilamentSpatieLaravelBackup\Support\CachedBackupDestination;
use Spatie\Backup\BackupDestination\BackupCollection;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Tasks\Monitor\HealthCheck;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

class ConfigurablePassingHealthCheck extends HealthCheck
{
    public function __construct(protected bool $passes) {}

    public function checkHealth(BackupDestination $backupDestination): void
    {
        $this->failUnless($this->passes, 'The configured health check failed.');
    }
}

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
        new FileAttributes('test-app/custom.backup', 300, lastModified: 1_777_248_000, mimeType: 'application/zip'),
        new FileAttributes('test-app/readme.txt', 50, lastModified: 1_777_161_600),
    ]);

    Storage::shouldReceive('disk')->once()->with('remote')->andReturn($filesystem);
    $filesystem->shouldReceive('getDriver')->once()->andReturn($driver);
    $filesystem->shouldNotReceive('exists');
    $filesystem->shouldNotReceive('size');
    $filesystem->shouldNotReceive('lastModified');
    $driver->shouldReceive('listContents')->once()->with('test-app', true)->andReturn($listing);

    $records = FilamentSpatieLaravelBackup::getBackupDestinationData('remote', cacheDuration: 0);

    expect($records)->toHaveCount(3)
        ->and($records[0]['path'])->toBe('test-app/2026-08-24-00-00-00.zip')
        ->and($records[1]['path'])->toBe('test-app/2026-08-23-00-00-00.zip')
        ->and($records[2]['path'])->toBe('test-app/custom.backup')
        ->and($records[2]['size'])->toBe('300 B');
});

it('uses filename timestamps for typed backups when modification times differ', function () {
    $driver = Mockery::mock(FilesystemOperator::class);
    $filesystem = Mockery::mock(FilesystemAdapter::class);
    $listing = new DirectoryListing([
        new FileAttributes(
            'test-app/only-db-2026-08-20-00-00-00.zip',
            100,
            lastModified: 2_000_000_000,
        ),
        new FileAttributes(
            'test-app/only-files-2026-08-21-00-00-00.zip',
            200,
            lastModified: 1,
        ),
    ]);

    Storage::shouldReceive('disk')->once()->with('remote')->andReturn($filesystem);
    $filesystem->shouldReceive('getDriver')->once()->andReturn($driver);
    $filesystem->shouldNotReceive('size');
    $filesystem->shouldNotReceive('lastModified');
    $driver->shouldReceive('listContents')->once()->with('test-app', true)->andReturn($listing);

    $records = FilamentSpatieLaravelBackup::getBackupDestinationData('remote', cacheDuration: 0);

    expect($records)->toHaveCount(2)
        ->and($records[0]['path'])->toBe('test-app/only-files-2026-08-21-00-00-00.zip')
        ->and($records[0]['date'])->toBe('2026-08-21 00:00:00')
        ->and($records[1]['path'])->toBe('test-app/only-db-2026-08-20-00-00-00.zip')
        ->and($records[1]['date'])->toBe('2026-08-20 00:00:00');
});

it('falls back to filesystem methods when directory metadata is unavailable', function () {
    $filesystem = Mockery::mock(Filesystem::class);

    Storage::shouldReceive('disk')->once()->with('legacy')->andReturn($filesystem);
    $filesystem->shouldReceive('allFiles')
        ->once()
        ->with('test-app')
        ->andReturn(['test-app/custom-name.zip', 'test-app/readme.txt']);
    $filesystem->shouldReceive('lastModified')
        ->once()
        ->with('test-app/custom-name.zip')
        ->andReturn(1_777_248_000);
    $filesystem->shouldReceive('size')
        ->once()
        ->with('test-app/custom-name.zip')
        ->andReturn(512);

    $records = FilamentSpatieLaravelBackup::getBackupDestinationData('legacy', cacheDuration: 0);

    expect($records)->toHaveCount(1)
        ->and($records[0]['path'])->toBe('test-app/custom-name.zip')
        ->and($records[0]['size'])->toBe('512 B');
});

it('resolves disk filter labels from configuration', function () {
    config()->set('backup.backup.destination.disks', ['backups', 'cold-storage']);

    expect(FilamentSpatieLaravelBackup::getFilterDisks())->toBe([
        'backups' => 'Backups',
        'cold-storage' => 'Cold-storage',
    ]);
});

it('ignores malformed disk monitor and health check configuration', function () {
    config()->set('backup.backup.destination.disks', 'backups');
    config()->set('backup.monitor_backups', 'test-app');

    expect(FilamentSpatieLaravelBackup::getDisks())->toBe([])
        ->and(FilamentSpatieLaravelBackup::getBackupDestinationStatusData(cacheDuration: 0))->toBe([]);

    config()->set('backup.backup.destination.disks', ['backups', null]);
    config()->set('backup.monitor_backups', [
        'invalid monitor',
        ['name' => null, 'disks' => []],
        ['name' => 'missing-disks'],
        ['name' => 'invalid-checks', 'disks' => [], 'health_checks' => 'invalid'],
        ['name' => 'invalid-class', 'disks' => [], 'health_checks' => [stdClass::class => 1]],
    ]);

    expect(FilamentSpatieLaravelBackup::getDisks())->toBe(['backups'])
        ->and(FilamentSpatieLaravelBackup::getBackupDestinationStatusData(cacheDuration: 0))->toBe([]);
});

it('detects backup types from filenames and provides translated filter options', function () {
    Storage::fake('backups');
    Storage::disk('backups')->put('test-app/only-db-2026-08-24-02-00-00.zip', 'database');
    Storage::disk('backups')->put('test-app/only-files-2026-08-24-01-00-00.zip', 'files');
    Storage::disk('backups')->put('test-app/2026-08-24-00-00-00.zip', 'all');

    $types = collect(FilamentSpatieLaravelBackup::getBackupDestinationData('backups', cacheDuration: 0))
        ->pluck('type', 'path')
        ->all();

    expect($types)->toMatchArray([
        'test-app/only-db-2026-08-24-02-00-00.zip' => 'only-db',
        'test-app/only-files-2026-08-24-01-00-00.zip' => 'only-files',
        'test-app/2026-08-24-00-00-00.zip' => 'db-and-files',
    ])->and(FilamentSpatieLaravelBackup::detectBackupType('only-db-app/2026-08-24-00-00-00.zip'))
        ->toBe(BackupType::DATABASE_AND_FILES)
        ->and(FilamentSpatieLaravelBackup::detectBackupType('backup-only-files-2026-08-24.zip'))
        ->toBe(BackupType::DATABASE_AND_FILES)
        ->and(FilamentSpatieLaravelBackup::detectBackupType('only-db-2026-08-24.zip'))
        ->toBe(BackupType::ONLY_DATABASE)
        ->and(FilamentSpatieLaravelBackup::getFilterTypes())->toBe([
            'only-db' => 'Only DB',
            'only-files' => 'Only Files',
            'db-and-files' => 'DB & Files',
        ]);
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

    FilamentSpatieLaravelBackup::clearBackupDestinationCache('backups', 'test-app');

    expect(FilamentSpatieLaravelBackup::getBackupDestinationData('backups', cacheDuration: 60))
        ->toHaveCount(2);
});

it('invalidates every configured and monitored destination cache', function () {
    config()->set('backup.monitor_backups', [[
        'name' => 'monitored-app',
        'disks' => ['archive', 'backups'],
    ]]);

    $keys = [
        'filament-spatie-backup:snapshot:' . hash('sha256', "backups\0test-app"),
        'filament-spatie-backup:snapshot:' . hash('sha256', "archive\0monitored-app"),
        'filament-spatie-backup:snapshot:' . hash('sha256', "backups\0monitored-app"),
        'backups-backups',
        'backups-archive',
        'backup-statuses',
    ];

    foreach ($keys as $key) {
        Cache::put($key, true, 60);
    }

    FilamentSpatieLaravelBackup::clearBackupDestinationCaches();

    foreach ($keys as $key) {
        expect(Cache::has($key))->toBeFalse();
    }
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
            MaximumAgeInDays::class,
            MaximumAgeInDays::class => 1,
            MaximumStorageInMegabytes::class => 1,
            ConfigurablePassingHealthCheck::class => ['passes' => true],
        ],
    ]]);

    $statuses = FilamentSpatieLaravelBackup::getBackupDestinationStatusData(cacheDuration: 0);

    expect($statuses[0]['healthy'])->toBeTrue();
});

it('reports unreachable destinations without trying to inspect their backups', function () {
    config()->set('filesystems.disks.broken', ['driver' => 'unsupported']);
    config()->set('backup.monitor_backups', [[
        'name' => 'test-app',
        'disks' => ['broken'],
        'health_checks' => [],
    ]]);

    $statuses = FilamentSpatieLaravelBackup::getBackupDestinationStatusData(cacheDuration: 0);

    expect($statuses)->toHaveCount(1)
        ->and($statuses[0]['reachable'])->toBeFalse()
        ->and($statuses[0]['healthy'])->toBeFalse()
        ->and($statuses[0]['amount'])->toBe(0)
        ->and($statuses[0]['newest'])->toBe('No backups present');
});

it('uses a default connection error for unreachable cached destinations', function () {
    $destination = new CachedBackupDestination(null, 'test-app', 'broken');

    $destination->useSnapshot(new BackupCollection, false);

    expect($destination->isReachable())->toBeFalse()
        ->and($destination->backups())->toBeEmpty()
        ->and($destination->connectionError?->getMessage())
        ->toBe('The backup destination is not reachable.');
});
