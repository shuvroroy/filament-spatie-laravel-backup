<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\BackupType;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;
use Spatie\Backup\Commands\BackupCommand;

class CreateBackupJobWithoutTimeLimit extends CreateBackupJob
{
    protected function canSetTimeLimit(): bool
    {
        return false;
    }
}

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
            && is_string($arguments['--filename'])
            && str_starts_with($arguments['--filename'], 'only-db-')
        ))
        ->andReturn(0);
    Artisan::swap($artisan);

    $job = new CreateBackupJob(BackupType::ONLY_DATABASE, timeout: 0);

    expect($job->timeout)->toBe(0);

    $job->handle();
});

it('keeps the queue timeout without calling an unavailable PHP time limit function', function () {
    $artisan = Mockery::mock(Kernel::class);
    $artisan->shouldReceive('call')
        ->once()
        ->with(BackupCommand::class, Mockery::on(
            fn (array $arguments): bool => $arguments['--only-db'] === false
            && $arguments['--only-files'] === true
            && $arguments['--timeout'] === null
            && is_string($arguments['--filename'])
            && str_starts_with($arguments['--filename'], 'only-files-')
        ))
        ->andReturn(0);
    Artisan::swap($artisan);

    $job = new CreateBackupJobWithoutTimeLimit(BackupType::ONLY_FILES, timeout: 120);

    expect($job->timeout)->toBe(120);

    $job->handle();
});

it('fails the queued job when the backup command fails', function () {
    $artisan = Mockery::mock(Kernel::class);
    $artisan->shouldReceive('call')->once()->andReturn(1);
    Artisan::swap($artisan);

    (new CreateBackupJob)->handle();
})->throws(RuntimeException::class, 'The backup command failed with exit code 1.');

it('reports cache invalidation failures without repeating a successful backup', function () {
    $artisan = Mockery::mock(Kernel::class);
    $artisan->shouldReceive('call')->once()->andReturn(0);
    Artisan::swap($artisan);

    $exception = new RuntimeException('Cache is unavailable.');
    Cache::shouldReceive('forget')
        ->once()
        ->with('backup-statuses')
        ->andThrow($exception);

    $handler = Mockery::mock(ExceptionHandler::class);
    $handler->shouldReceive('report')->once()->with($exception);
    app()->instance(ExceptionHandler::class, $handler);

    (new CreateBackupJob)->handle();
});
