<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Pages;

use Filament\Actions\Action;
use Filament\Clusters\Cluster;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Pages\PageConfiguration;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\BackupType;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;

class Backups extends Page
{
    protected string $view = 'filament-spatie-backup::pages.backups';

    public function getHeading(): string | Htmlable
    {
        return FilamentSpatieLaravelBackupPlugin::get()->getHeading();
    }

    public static function registerRoutes(Panel $panel, ?PageConfiguration $configuration = null): void
    {
        $currentPanel = Filament::getCurrentPanel();

        Filament::setCurrentPanel($panel);

        try {
            parent::registerRoutes($panel, $configuration);
        } finally {
            Filament::setCurrentPanel($currentPanel);
        }
    }

    /** @return class-string<Cluster>|null */
    public static function getCluster(): ?string
    {
        return FilamentSpatieLaravelBackupPlugin::get()->getClusterName();
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        if (static::getCluster() !== null) {
            return null;
        }

        return FilamentSpatieLaravelBackupPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return FilamentSpatieLaravelBackupPlugin::get()->getNavigationLabel();
    }

    public static function getNavigationSort(): ?int
    {
        return FilamentSpatieLaravelBackupPlugin::get()->getNavigationSort();
    }

    public static function getNavigationIcon(): string | \BackedEnum | null
    {
        return FilamentSpatieLaravelBackupPlugin::get()->getNavigationIcon();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('Create Backup')
                ->button()
                ->label(__('filament-spatie-backup::backup.pages.backups.actions.create_backup'))
                ->action('openOptionModal')
                ->visible(auth()->user()?->can('create-backup') ?? false),
        ];
    }

    public function openOptionModal(): void
    {
        $this->dispatch('open-modal', id: 'backup-option');
    }

    public function create(string $type = BackupType::DATABASE_AND_FILES->value): void
    {
        $backupType = BackupType::tryFrom($type);

        if ($backupType === null) {
            Notification::make()
                ->title(__('filament-spatie-backup::backup.pages.backups.modal.label'))
                ->danger()
                ->send();

            return;
        }

        /** @var FilamentSpatieLaravelBackupPlugin $plugin */
        $plugin = filament()->getPlugin('filament-spatie-backup');

        CreateBackupJob::dispatch($backupType, $plugin->getTimeout())
            ->onConnection($plugin->getQueueConnection())
            ->onQueue($plugin->getQueue());

        $this->dispatch('close-modal', id: 'backup-option');

        Notification::make()
            ->title(__('filament-spatie-backup::backup.pages.backups.messages.backup_success'))
            ->success()
            ->send();
    }

    public function shouldDisplayStatusListRecords(): bool
    {
        /** @var FilamentSpatieLaravelBackupPlugin $plugin */
        $plugin = filament()->getPlugin('filament-spatie-backup');

        return $plugin->hasStatusListRecordsTable();
    }

    public static function canAccess(): bool
    {
        return FilamentSpatieLaravelBackupPlugin::get()->isAuthorized();
    }
}
