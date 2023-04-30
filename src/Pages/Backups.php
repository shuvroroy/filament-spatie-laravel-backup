<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Pages;

use Filament\Pages\Actions;
use Filament\Pages\Page;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;

class Backups extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-support';

    protected static string $view = 'filament-spatie-backup::pages.backups';

    private bool $hideBackupStatusTable = false;

    protected function getHeading(): string
    {
        return __('filament-spatie-backup::backup.pages.backups.heading');
    }

    protected static function getNavigationGroup(): ?string
    {
        return __('filament-spatie-backup::backup.pages.backups.navigation.group');
    }

    protected static function getNavigationLabel(): string
    {
        return __('filament-spatie-backup::backup.pages.backups.navigation.label');
    }

    protected function getActions(): array
    {
        return [
            Actions\Action::make('Create Backup')
                ->button()
                ->label(__('filament-spatie-backup::backup.pages.backups.actions.create_backup'))
                ->action('openOptionModal')
                ->visible(auth()->user()->can('create-backup')),
        ];
    }

    public function openOptionModal(): void
    {
        $this->dispatchBrowserEvent('open-modal', ['id' => 'backup-option']);
    }

    public function create(string $option = ''): void
    {
        dispatch(new CreateBackupJob($option))
            ->onQueue(config('filament-spatie-laravel-backup.queue'))
            ->afterResponse();

        $this->dispatchBrowserEvent('close-modal', ['id' => 'backup-option']);

        $this->notify('success', __('filament-spatie-backup::backup.pages.backups.messages.backup_success'));
    }

    public function hideBackupStatusTable(bool $condition = true): void
    {
        $this->hideBackupStatusTable = $condition;
    }

    public function getHideBackupStatusTable(): bool
    {
        return $this->hideBackupStatusTable;
    }
}
