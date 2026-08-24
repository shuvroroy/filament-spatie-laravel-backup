<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup;

use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;
use League\Flysystem\StorageAttributes;
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

    public static function getDisks(): array
    {
        return config('backup.backup.destination.disks');
    }

    public static function getDisk(): string
    {
        $defaultDisks = static::getDisks();

        return request('tableFilters.disk.value', reset($defaultDisks));
    }

    public static function getFilterDisks(): array
    {
        $result = [];

        foreach (static::getDisks() as $value) {
            $result[$value] = ucfirst($value);
        }

        return $result;
    }

    /**
     * @return array<int, array{key: string, disk: string, path: string, date: string, size: string}>
     */
    public static function getBackupDestinationData(
        string $disk,
        int $cacheDuration = self::DEFAULT_CACHE_DURATION,
    ): array {
        $snapshot = static::getBackupDestinationSnapshot(
            $disk,
            config('backup.backup.name'),
            $cacheDuration,
        );

        return collect($snapshot['backups'])
            ->map(function (array $backup) use ($disk): array {
                return [
                    'key' => sha1($disk . "\0" . $backup['path']),
                    'disk' => $disk,
                    'path' => $backup['path'],
                    'date' => Carbon::createFromTimestamp($backup['timestamp'])
                        ->setTimezone(config('app.timezone'))
                        ->format('Y-m-d H:i:s'),
                    'size' => Format::humanReadableSize($backup['size']),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{id: int|string, name: string, disk: string, reachable: bool, healthy: bool, amount: int, newest: string, usedStorage: string}>
     */
    public static function getBackupDestinationStatusData(
        int $cacheDuration = self::DEFAULT_CACHE_DURATION,
    ): array {
        return collect(config('backup.monitor_backups', []))
            ->flatMap(function (array $monitor) use ($cacheDuration) {
                $name = $monitor['name'];
                $healthChecks = static::makeHealthChecks(
                    $monitor['health_checks'] ?? $monitor['healthChecks'] ?? [],
                );

                return collect($monitor['disks'])
                    ->map(function (string $disk) use ($cacheDuration, $healthChecks, $name): array {
                        $snapshot = static::getBackupDestinationSnapshot($disk, $name, $cacheDuration);

                        /** @var CachedBackupDestination $destination */
                        $destination = CachedBackupDestination::create($disk, $name);
                        $reachable = $snapshot['reachable'] && $destination->connectionError === null;
                        $backups = new BackupCollection;

                        if ($reachable) {
                            $filesystem = $destination->disk();
                            $backups = new BackupCollection(
                                collect($snapshot['backups'])
                                    ->map(fn (array $backup): CachedBackup => new CachedBackup(
                                        $filesystem,
                                        $backup['path'],
                                        Carbon::createFromTimestamp($backup['timestamp']),
                                        $backup['size'],
                                    ))
                                    ->all(),
                            );
                        }

                        $destination->useSnapshot($backups, $reachable, $snapshot['error']);

                        $cachedStatus = new BackupDestinationStatus($destination, $healthChecks);
                        $newestBackup = $destination->newestBackup();

                        return [
                            'id' => sha1($disk . "\0" . $name),
                            'name' => $name,
                            'disk' => $disk,
                            'reachable' => $reachable,
                            'healthy' => $cachedStatus->isHealthy(),
                            'amount' => $backups->count(),
                            'newest' => $newestBackup
                                ? $newestBackup->date()->diffForHumans()
                                : __('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.no_backups_present'),
                            'usedStorage' => Format::humanReadableSize($backups->size()),
                        ];
                    });
            })
            ->sortBy(fn (array $status): string => $status['name'] . '-' . $status['disk'])
            ->values()
            ->toArray();
    }

    /**
     * @param  array<class-string<HealthCheck>|int, class-string<HealthCheck>|int|array<string, mixed>>  $configuredHealthChecks
     * @return array<int, HealthCheck>
     */
    protected static function makeHealthChecks(array $configuredHealthChecks): array
    {
        return collect($configuredHealthChecks)
            ->map(function (string | int | array $options, string | int $class): HealthCheck {
                if (is_int($class)) {
                    $class = $options;
                    $options = [];
                }

                if (! is_array($options)) {
                    return new $class($options);
                }

                return app()->makeWith($class, $options);
            })
            ->values()
            ->all();
    }

    public static function clearCachedBackupDestinationData(
        ?string $disk = null,
        ?string $backupName = null,
    ): void {
        if ($disk !== null && $backupName !== null) {
            Cache::forget(static::snapshotCacheKey($disk, $backupName));
            Cache::forget('backups-' . $disk);

            return;
        }

        $destinations = collect(static::getDisks())
            ->map(fn (string $configuredDisk): array => [
                'disk' => $configuredDisk,
                'name' => config('backup.backup.name'),
            ]);

        foreach (config('backup.monitor_backups', []) as $monitor) {
            foreach ($monitor['disks'] ?? [] as $monitoredDisk) {
                $destinations->push([
                    'disk' => $monitoredDisk,
                    'name' => $monitor['name'],
                ]);
            }
        }

        $destinations
            ->unique(fn (array $destination): string => $destination['disk'] . "\0" . $destination['name'])
            ->each(function (array $destination): void {
                Cache::forget(static::snapshotCacheKey($destination['disk'], $destination['name']));
                Cache::forget('backups-' . $destination['disk']);
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
            return $filesystem->getDriver()
                ->listContents($backupName, true)
                ->toArray();
        }

        return $filesystem->allFiles($backupName);
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
        try {
            $date = Carbon::createFromFormat(BackupJob::FILENAME_FORMAT, basename($path));

            if ($date !== null) {
                return $date->timestamp;
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
