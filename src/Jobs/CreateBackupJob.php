<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\BackupType;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use Spatie\Backup\Commands\BackupCommand;
use Symfony\Component\Console\Command\Command;
use Throwable;

class CreateBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        protected readonly BackupType $type = BackupType::DATABASE_AND_FILES,
        public readonly ?int $timeout = null,
    ) {}

    public function handle(): void
    {
        $canSetTimeLimit = $this->canSetTimeLimit();

        if ($this->timeout !== null && $canSetTimeLimit) {
            set_time_limit($this->timeout);
        }

        $exitCode = Artisan::call(BackupCommand::class, [
            '--only-db' => $this->type === BackupType::ONLY_DATABASE,
            '--only-files' => $this->type === BackupType::ONLY_FILES,
            '--filename' => match ($this->type) {
                BackupType::DATABASE_AND_FILES => null,
                default => $this->type->value . '-' . date('Y-m-d-H-i-s') . '.zip',
            },
            '--timeout' => $canSetTimeLimit ? $this->timeout : null,
        ]);

        if ($exitCode !== Command::SUCCESS) {
            throw new RuntimeException("The backup command failed with exit code {$exitCode}.");
        }

        try {
            FilamentSpatieLaravelBackup::clearBackupDestinationCaches();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function canSetTimeLimit(): bool
    {
        return function_exists('set_time_limit');
    }
}
