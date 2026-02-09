<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups;

it('passes smoke test', function () {
    $panel = Filament::getCurrentOrDefaultPanel();

    $panel->plugins([
        FilamentSpatieLaravelBackupPlugin::make(),
    ]);

    expect($panel->getPlugin('filament-spatie-backup'))
        ->toBeInstanceOf(FilamentSpatieLaravelBackupPlugin::class);

    expect($panel->getPages())->toContain(Backups::class);
});
