<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\Option;
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
        protected readonly Option $option = Option::ALL,
        public readonly ?int $timeout = null,
    ) {}

    public function handle(): void
    {
        if ($this->timeout !== null) {
            set_time_limit($this->timeout);
        }

        $exitCode = Artisan::call(BackupCommand::class, [
            '--only-db' => $this->option === Option::ONLY_DB,
            '--only-files' => $this->option === Option::ONLY_FILES,
            '--filename' => match ($this->option) {
                Option::ALL => null,
                default => str_replace('_', '-', $this->option->value) .
                    '-' . date('Y-m-d-H-i-s') . '.zip'
            },
            '--timeout' => $this->timeout,
        ]);

        if ($exitCode !== Command::SUCCESS) {
            throw new RuntimeException("The backup command failed with exit code {$exitCode}.");
        }

        try {
            FilamentSpatieLaravelBackup::clearCachedBackupDestinationData();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
