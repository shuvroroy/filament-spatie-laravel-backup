<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Enums;

enum BackupType: string
{
    case ONLY_DATABASE = 'only-db';
    case ONLY_FILES = 'only-files';
    case DATABASE_AND_FILES = 'db-and-files';
}
