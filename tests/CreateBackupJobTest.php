<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\Option;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;
use Spatie\Backup\Commands\BackupCommand;

beforeEach(function () {
    config()->set('backup.backup.destination.disks', []);
    config()->set('backup.monitor_backups', []);
});

it('passes backup options and exposes the timeout to Laravel queue workers', function () {
    $artisan = Mockery::mock(Kernel::class);
    $artisan->shouldReceive('call')
        ->once()
        ->with(BackupCommand::class, Mockery::on(
            fn (array $arguments): bool => $arguments['--only-db'] === true
            && $arguments['--only-files'] === false
            && $arguments['--timeout'] === 0
            && str_starts_with($arguments['--filename'], 'only-db-')
        ))
        ->andReturn(0);
    Artisan::swap($artisan);

    $job = new CreateBackupJob(Option::ONLY_DB, timeout: 0);

    expect($job->timeout)->toBe(0);

    $job->handle();
});

it('fails the queued job when the backup command fails', function () {
    $artisan = Mockery::mock(Kernel::class);
    $artisan->shouldReceive('call')->once()->andReturn(1);
    Artisan::swap($artisan);

    (new CreateBackupJob)->handle();
})->throws(RuntimeException::class, 'The backup command failed with exit code 1.');
