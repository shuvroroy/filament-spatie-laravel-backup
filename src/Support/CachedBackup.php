<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Support;

use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Spatie\Backup\BackupDestination\Backup;

/**
 * A backup whose metadata came from a single filesystem directory listing.
 *
 * Spatie's Backup constructor checks every file individually. Avoiding that
 * check is important for remote disks, where it results in one request per
 * backup before the table can render.
 */
class CachedBackup extends Backup
{
    public function __construct(
        Filesystem $disk,
        string $path,
        protected Carbon $backupDate,
        protected float $backupSize,
    ) {
        $this->disk = $disk;
        $this->path = $path;
        $this->exists = true;
    }

    public function date(): Carbon
    {
        return $this->backupDate->copy();
    }

    public function sizeInBytes(): float
    {
        return $this->backupSize;
    }
}
