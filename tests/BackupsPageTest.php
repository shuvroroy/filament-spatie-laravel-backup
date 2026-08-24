<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\Bus;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups;

it('dispatches backup jobs immediately to the configured queue', function () {
    Bus::fake();

    $panel = Panel::make()
        ->default()
        ->id('test')
        ->plugin(
            FilamentSpatieLaravelBackupPlugin::make()
                ->usingQueueConnection('redis')
                ->usingQueue('backups')
                ->timeout(120),
        );

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);

    app(Backups::class)->create('only-db');

    Bus::assertDispatched(
        CreateBackupJob::class,
        fn (CreateBackupJob $job): bool => $job->connection === 'redis'
        && $job->queue === 'backups'
        && $job->timeout === 120
    );
    Bus::assertNotDispatchedAfterResponse(CreateBackupJob::class);
});
