<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Models;

use Illuminate\Database\Eloquent\Model;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use Sushi\Sushi;

class BackupDestinationStatus extends Model
{
    use Sushi;

    public function getRows(): array
    {
        return FilamentSpatieLaravelBackup::getBackupDestinationStatusData();
    }
}
