<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;

class CreateBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    protected string $option;

    public function __construct(string $option = '')
    {
        $this->option = $option;
    }

    public function handle()
    {
        $backupJob = $this->backupConfig();

        if (PHP_OS === 'WINNT') {
            $backupJob->disableSignals();
        }

        if ($this->option === 'only-db') {
            $backupJob->dontBackupFilesystem();
        }

        if ($this->option === 'only-files') {
            $backupJob->dontBackupDatabases();
        }

        if (!empty($this->option)) {
            $prefix = str_replace('_', '-', $this->option) . '-';

            $backupJob->setFilename($prefix . date('Y-m-d-H-i-s') . '.zip');
        }

        $backupJob->run();
    }

    private function backupConfig()
    {
        if ($this->isTenant()) {
            $tenant = tenant();
            $key = $this->getConfigTenant()->key;
            Config::set('backup.backup.name', Str::ucfirst($tenant->$key));
            Config::set('database.connections.mysql.database', $tenant->tenancy_db_name);
            Config::set('backup.backup.source.files.include', storage_path('app'));
        }

        return BackupJobFactory::createFromArray(config('backup'));
    }

    private function getConfigTenant(): object
    {
        return (object)config('filament-spatie-laravel-backup.tenant');
    }

    private function isTenant(): bool
    {
        return ($this->getConfigTenant()->active) && (function_exists('tenant') && tenant());
    }
}
