<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Enums;

enum Option: string
{
    case ONLY_DB = 'only-db';
    case ONLY_FILES = 'only-files';
    case ALL = '';
}
