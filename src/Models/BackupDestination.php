<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Models;

use Illuminate\Database\Eloquent\Model;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use Sushi\Sushi;

/**
 * @property string $path
 * @property string $disk
 */
class BackupDestination extends Model
{
    use Sushi;

    public function getRows(): array
    {
        $data = [];

        foreach (FilamentSpatieLaravelBackup::getDisks() as $disk) {
            $data = array_merge($data, FilamentSpatieLaravelBackup::getBackupDestinationData($disk));
        }

        return $data;
    }
}
