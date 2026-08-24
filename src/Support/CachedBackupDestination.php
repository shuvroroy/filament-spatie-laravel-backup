<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Support;

use RuntimeException;
use Spatie\Backup\BackupDestination\BackupCollection;
use Spatie\Backup\BackupDestination\BackupDestination;

class CachedBackupDestination extends BackupDestination
{
    protected ?BackupCollection $cachedBackups = null;

    protected ?bool $cachedReachability = null;

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
