<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup;

use Illuminate\Support\Facades\Cache;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Config\MonitoredBackupsConfig;
use Spatie\Backup\Helpers\Format;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatus;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatusFactory;

class FilamentSpatieLaravelBackup
{
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

    public static function getBackupDestinationData(string $disk): array
    {
        return Cache::remember('backups-' . $disk, now()->addSeconds(4), function () use ($disk) {
            return BackupDestination::create($disk, config('backup.backup.name'))
                ->backups()
                ->map(function (Backup $backup) use ($disk) {
                    return [
                        'disk' => $disk,
                        'path' => $backup->path(),
                        'type' => static::detectBackupType($disk, $backup->path()),
                        'date' => $backup->date()->format('Y-m-d H:i:s'),
                        'size' => Format::humanReadableSize($backup->sizeInBytes()),
                    ];
                })
                ->toArray();
        });
    }

    public static function detectBackupType(string $disk, string $path): string
    {
        $filename = basename($path);

        if (str_contains($filename, 'only-db')) {
            return 'only-db';
        }

        if (str_contains($filename, 'only-files')) {
            return 'only-files';
        }

        return 'db-and-files';
    }

    public static function getFilterTypes(): array
    {
        return [
            'only-db' => __('filament-spatie-backup::backup.pages.backups.modal.buttons.only_db'),
            'only-files' => __('filament-spatie-backup::backup.pages.backups.modal.buttons.only_files'),
            'db-and-files' => __('filament-spatie-backup::backup.pages.backups.modal.buttons.db_and_files'),
        ];
    }

    public static function getBackupDestinationStatusData(): array
    {
        return Cache::remember('backup-statuses', now()->addSeconds(4), function () {
            $config = class_exists('Spatie\Backup\Config\MonitoredBackupsConfig')
                ? MonitoredBackupsConfig::fromArray(config('backup.monitor_backups'))
                : config('backup.monitor_backups');

            return BackupDestinationStatusFactory::createForMonitorConfig($config)
                ->map(function (BackupDestinationStatus $backupDestinationStatus, int | string $key) {
                    return [
                        'id' => $key,
                        'name' => $backupDestinationStatus->backupDestination()->backupName(),
                        'disk' => $backupDestinationStatus->backupDestination()->diskName(),
                        'reachable' => $backupDestinationStatus->backupDestination()->isReachable(),
                        'healthy' => $backupDestinationStatus->isHealthy(),
                        'amount' => $backupDestinationStatus->backupDestination()->backups()->count(),
                        'newest' => $backupDestinationStatus->backupDestination()->newestBackup()
                            ? $backupDestinationStatus->backupDestination()->newestBackup()->date()->diffForHumans()
                            : __('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.no_backups_present'),
                        'usedStorage' => Format::humanReadableSize($backupDestinationStatus->backupDestination()->usedStorage()),
                    ];
                })
                ->values()
                ->toArray();
        });
    }
}
