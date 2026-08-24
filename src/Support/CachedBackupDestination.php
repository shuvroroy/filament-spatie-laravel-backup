<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Support;

use Exception;
use Illuminate\Contracts\Filesystem\Factory;
use RuntimeException;
use Spatie\Backup\BackupDestination\BackupCollection;
use Spatie\Backup\BackupDestination\BackupDestination;

final class CachedBackupDestination extends BackupDestination
{
    protected ?BackupCollection $cachedBackups = null;

    protected ?bool $cachedReachability = null;

    public static function create(string $diskName, string $backupName): self
    {
        try {
            $disk = app(Factory::class)->disk($diskName);

            return new self($disk, $backupName, $diskName);
        } catch (Exception $exception) {
            $destination = new self(null, $backupName, $diskName);
            $destination->connectionError = $exception;

            return $destination;
        }
    }

    public function useSnapshot(
        BackupCollection $backups,
        bool $reachable,
        ?string $connectionError = null,
    ): static {
        $this->cachedBackups = $backups;
        $this->cachedReachability = $reachable;

        if (! $reachable) {
            $this->connectionError = new RuntimeException($connectionError ?? 'The backup destination is not reachable.');
        }

        return $this;
    }

    public function backups(): BackupCollection
    {
        return $this->cachedBackups ?? parent::backups();
    }

    public function isReachable(): bool
    {
        return $this->cachedReachability ?? parent::isReachable();
    }
}
