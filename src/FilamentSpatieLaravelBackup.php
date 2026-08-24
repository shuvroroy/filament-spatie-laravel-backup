<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup;

use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;
use League\Flysystem\StorageAttributes;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\BackupType;
use ShuvroRoy\FilamentSpatieLaravelBackup\Support\CachedBackup;
use ShuvroRoy\FilamentSpatieLaravelBackup\Support\CachedBackupDestination;
use Spatie\Backup\BackupDestination\BackupCollection;
use Spatie\Backup\Helpers\Format;
use Spatie\Backup\Tasks\Backup\BackupJob;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatus;
use Spatie\Backup\Tasks\Monitor\HealthCheck;
use Throwable;

class FilamentSpatieLaravelBackup
{
    public const DEFAULT_CACHE_DURATION = 30;

    /** @return list<string> */
    public static function getDisks(): array
    {
        $configuredDisks = config('backup.backup.destination.disks', []);

        if (! is_array($configuredDisks)) {
            return [];
        }

        $disks = [];

        foreach ($configuredDisks as $disk) {
            if (is_string($disk)) {
                $disks[] = $disk;
            }
        }

        return $disks;
    }

    /** @return array<string, string> */
    public static function getFilterDisks(): array
    {
        $result = [];

        foreach (static::getDisks() as $value) {
            $result[$value] = ucfirst($value);
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public static function getFilterTypes(): array
    {
        return [
            BackupType::ONLY_DATABASE->value => __('filament-spatie-backup::backup.pages.backups.modal.buttons.only_db'),
            BackupType::ONLY_FILES->value => __('filament-spatie-backup::backup.pages.backups.modal.buttons.only_files'),
            BackupType::DATABASE_AND_FILES->value => __('filament-spatie-backup::backup.pages.backups.modal.buttons.db_and_files'),
        ];
    }

    public static function detectBackupType(string $path): BackupType
    {
        $filename = basename($path);

        if (str_starts_with($filename, BackupType::ONLY_DATABASE->value . '-')) {
            return BackupType::ONLY_DATABASE;
        }

        if (str_starts_with($filename, BackupType::ONLY_FILES->value . '-')) {
            return BackupType::ONLY_FILES;
        }

        return BackupType::DATABASE_AND_FILES;
    }

    /**
     * @return list<array{key: string, disk: string, path: string, type: string, date: string, size: string}>
     */
    public static function getBackupDestinationData(
        string $disk,
        int $cacheDuration = self::DEFAULT_CACHE_DURATION,
    ): array {
        $snapshot = static::getBackupDestinationSnapshot(
            $disk,
            config()->string('backup.backup.name'),
            $cacheDuration,
        );

        $records = [];

        foreach ($snapshot['backups'] as $backup) {
            $records[] = [
                'key' => sha1($disk . "\0" . $backup['path']),
                'disk' => $disk,
                'path' => $backup['path'],
                'type' => static::detectBackupType($backup['path'])->value,
                'date' => Carbon::createFromTimestamp($backup['timestamp'])
                    ->setTimezone(config()->string('app.timezone', 'UTC'))
                    ->format('Y-m-d H:i:s'),
                'size' => Format::humanReadableSize($backup['size']),
            ];
        }

        return $records;
    }

    /**
     * @return list<array{id: string, name: string, disk: string, reachable: bool, healthy: bool, amount: int, newest: string, usedStorage: string}>
     */
    public static function getBackupDestinationStatusData(
        int $cacheDuration = self::DEFAULT_CACHE_DURATION,
    ): array {
        $statuses = [];

        foreach (static::getMonitorConfigurations() as $monitor) {
            $name = $monitor['name'];
            $healthChecks = static::makeHealthChecks($monitor['health_checks']);

            foreach ($monitor['disks'] as $disk) {
                $snapshot = static::getBackupDestinationSnapshot($disk, $name, $cacheDuration);

                $destination = CachedBackupDestination::create($disk, $name);
                $reachable = $snapshot['reachable'] && $destination->connectionError === null;
                $backups = new BackupCollection;

                if ($reachable) {
                    $filesystem = $destination->disk();
                    $cachedBackups = [];

                    foreach ($snapshot['backups'] as $backup) {
                        $cachedBackups[] = new CachedBackup(
                            $filesystem,
                            $backup['path'],
                            Carbon::createFromTimestamp($backup['timestamp']),
                            $backup['size'],
                        );
                    }

                    $backups = new BackupCollection($cachedBackups);
                }

                $destination->useSnapshot($backups, $reachable, $snapshot['error']);

                $cachedStatus = new BackupDestinationStatus($destination, $healthChecks);
                $newestBackup = $destination->newestBackup();
                $newest = $newestBackup
                    ? $newestBackup->date()->diffForHumans()
                    : __('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.no_backups_present');

                $statuses[] = [
                    'id' => sha1($disk . "\0" . $name),
                    'name' => $name,
                    'disk' => $disk,
                    'reachable' => $reachable,
                    'healthy' => $cachedStatus->isHealthy(),
                    'amount' => $backups->count(),
                    'newest' => $newest,
                    'usedStorage' => Format::humanReadableSize($backups->size()),
                ];
            }
        }

        usort(
            $statuses,
            fn (array $left, array $right): int => [$left['name'], $left['disk']] <=> [$right['name'], $right['disk']],
        );

        return $statuses;
    }

    /**
     * @return list<HealthCheck>
     */
    protected static function makeHealthChecks(mixed $configuredHealthChecks): array
    {
        if (! is_array($configuredHealthChecks)) {
            return [];
        }

        $healthChecks = [];

        foreach ($configuredHealthChecks as $class => $options) {
            if (is_int($class)) {
                if (is_string($options) && is_a($options, HealthCheck::class, true)) {
                    $healthCheck = app()->make($options);

                    if ($healthCheck instanceof HealthCheck) {
                        $healthChecks[] = $healthCheck;
                    }
                }

                continue;
            }

            if (! is_a($class, HealthCheck::class, true)) {
                continue;
            }

            if (is_array($options)) {
                $healthCheck = app()->makeWith($class, $options);

                if ($healthCheck instanceof HealthCheck) {
                    $healthChecks[] = $healthCheck;
                }

                continue;
            }

            if (is_int($options) || is_float($options) || is_string($options) || is_bool($options) || $options === null) {
                $healthChecks[] = new $class($options);
            }
        }

        return $healthChecks;
    }

    /**
     * @return list<array{name: string, disks: list<string>, health_checks: mixed}>
     */
    protected static function getMonitorConfigurations(): array
    {
        $configuredMonitors = config('backup.monitor_backups', []);

        if (! is_array($configuredMonitors)) {
            return [];
        }

        $monitors = [];

        foreach ($configuredMonitors as $monitor) {
            if (! is_array($monitor)) {
                continue;
            }

            $name = $monitor['name'] ?? null;
            $configuredDisks = $monitor['disks'] ?? null;

            if (! is_string($name) || ! is_array($configuredDisks)) {
                continue;
            }

            $disks = [];

            foreach ($configuredDisks as $disk) {
                if (is_string($disk)) {
                    $disks[] = $disk;
                }
            }

            $monitors[] = [
                'name' => $name,
                'disks' => $disks,
                'health_checks' => $monitor['health_checks'] ?? [],
            ];
        }

        return $monitors;
    }

    public static function clearBackupDestinationCache(string $disk, string $backupName): void
    {
        Cache::forget(static::snapshotCacheKey($disk, $backupName));
        Cache::forget('backups-' . $disk);
    }

    public static function clearBackupDestinationCaches(): void
    {
        $destinations = collect(static::getDisks())
            ->map(fn (string $configuredDisk): array => [
                'disk' => $configuredDisk,
                'name' => config()->string('backup.backup.name'),
            ]);

        foreach (static::getMonitorConfigurations() as $monitor) {
            foreach ($monitor['disks'] as $monitoredDisk) {
                $destinations->push([
                    'disk' => $monitoredDisk,
                    'name' => $monitor['name'],
                ]);
            }
        }

        $destinations
            ->unique(fn (array $destination): string => $destination['disk'] . "\0" . $destination['name'])
            ->each(function (array $destination): void {
                static::clearBackupDestinationCache($destination['disk'], $destination['name']);
            });

        Cache::forget('backup-statuses');
    }

    /**
     * @return array{reachable: bool, error: ?string, backups: array<int, array{path: string, timestamp: int, size: float}>}
     */
    protected static function getBackupDestinationSnapshot(
        string $disk,
        string $backupName,
        int $cacheDuration,
    ): array {
        $load = fn (): array => static::loadBackupDestinationSnapshot($disk, $backupName);

        if ($cacheDuration === 0) {
            return $load();
        }

        return Cache::remember(
            static::snapshotCacheKey($disk, $backupName),
            now()->addSeconds($cacheDuration),
            $load,
        );
    }

    /**
     * @return array{reachable: bool, error: ?string, backups: array<int, array{path: string, timestamp: int, size: float}>}
     */
    protected static function loadBackupDestinationSnapshot(string $disk, string $backupName): array
    {
        try {
            $filesystem = Storage::disk($disk);
            $backups = collect(static::listBackupAttributes($filesystem, $backupName))
                ->filter(function (StorageAttributes | string $attributes): bool {
                    if (is_string($attributes)) {
                        return static::isBackupFile($attributes);
                    }

                    return $attributes->isFile()
                        && static::isBackupFile(
                            $attributes->path(),
                            $attributes instanceof FileAttributes ? $attributes->mimeType() : null,
                        );
                })
                ->map(function (StorageAttributes | string $attributes) use ($filesystem): array {
                    $path = is_string($attributes) ? $attributes : $attributes->path();
                    $lastModified = is_string($attributes) ? null : $attributes->lastModified();
                    $size = $attributes instanceof FileAttributes ? $attributes->fileSize() : null;

                    return [
                        'path' => $path,
                        'timestamp' => static::backupTimestamp($filesystem, $path, $lastModified),
                        'size' => (float) ($size ?? $filesystem->size($path)),
                    ];
                })
                ->sort(function (array $left, array $right): int {
                    return [$right['timestamp'], $right['path']] <=> [$left['timestamp'], $left['path']];
                })
                ->values()
                ->all();

            return [
                'reachable' => true,
                'error' => null,
                'backups' => $backups,
            ];
        } catch (Throwable $exception) {
            return [
                'reachable' => false,
                'error' => $exception->getMessage(),
                'backups' => [],
            ];
        }
    }

    /**
     * @return array<int, StorageAttributes|string>
     */
    protected static function listBackupAttributes(Filesystem $filesystem, string $backupName): array
    {
        if ($filesystem instanceof FilesystemAdapter) {
            return array_values($filesystem->getDriver()
                ->listContents($backupName, true)
                ->toArray());
        }

        return array_values($filesystem->allFiles($backupName));
    }

    protected static function isBackupFile(string $path, ?string $mimeType = null): bool
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip') {
            return true;
        }

        return in_array($mimeType, [
            'application/zip',
            'application/x-zip',
            'application/x-gzip',
        ], true);
    }

    protected static function backupTimestamp(
        Filesystem $filesystem,
        string $path,
        ?int $lastModified,
    ): int {
        $filename = basename($path);
        $backupType = static::detectBackupType($filename);

        if ($backupType !== BackupType::DATABASE_AND_FILES) {
            $filename = substr($filename, strlen($backupType->value) + 1);
        }

        try {
            $date = Carbon::createFromFormat(BackupJob::FILENAME_FORMAT, $filename);

            if ($date !== null) {
                return (int) $date->timestamp;
            }
        } catch (Throwable) {
            // Custom backup filenames use the filesystem's modification time.
        }

        return $lastModified ?? $filesystem->lastModified($path);
    }

    protected static function snapshotCacheKey(string $disk, string $backupName): string
    {
        return 'filament-spatie-backup:snapshot:' . hash('sha256', $disk . "\0" . $backupName);
    }
}
