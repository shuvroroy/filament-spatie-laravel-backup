<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\Option;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;

class Backups extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog';

    protected static string $view = 'filament-spatie-backup::pages.backups';

    public function getHeading(): string | Htmlable
    {
        return __('filament-spatie-backup::backup.pages.backups.heading');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-spatie-backup::backup.pages.backups.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-spatie-backup::backup.pages.backups.navigation.label');
    }

    public function table(Table $table): Table
    {
        $plugin = filament()->getPlugin('filament-spatie-backup');

        return $table
            ->query(BackupDestination::query())
            ->columns([
                Tables\Columns\TextColumn::make('path')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.fields.path'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('disk')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.fields.disk'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.fields.date'))
                    ->dateTime()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('size')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.fields.size'))
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('disk')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.filters.disk'))
                    ->options(FilamentSpatieLaravelBackup::getFilterDisks()),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.actions.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn():bool => $plugin->getdownloadable())
                    ->action(fn (BackupDestination $record) => Storage::disk($record->disk)->download($record->path)),

                Tables\Actions\Action::make('delete')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.actions.delete'))
                    ->icon('heroicon-o-trash')
                    ->visible(fn():bool => $plugin->getdeletable())
                    ->requiresConfirmation()
                    ->action(function (BackupDestination $record) {
                        SpatieBackupDestination::create($record->disk, config('backup.backup.name'))
                            ->backups()
                            ->first(function (Backup $backup) use ($record) {
                                return $backup->path() === $record->path;
                            })
                            ->delete();

                        Notification::make()
                            ->title(__('filament-spatie-backup::backup.pages.backups.messages.backup_delete_success'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                // ...
            ])
            ->poll($plugin->getPolingInterval());
    }

    protected function getActions(): array
    {
        return [
            Action::make('Create Backup')
                ->button()
                ->label(__('filament-spatie-backup::backup.pages.backups.actions.create_backup'))
                ->action('openOptionModal'),
        ];
    }

    public function openOptionModal(): void
    {
        $this->dispatch('open-modal', id: 'backup-option');
    }

    public function create(string $option = ''): void
    {
        /** @var FilamentSpatieLaravelBackupPlugin $plugin */
        $plugin = filament()->getPlugin('filament-spatie-backup');

        CreateBackupJob::dispatch(Option::from($option), $plugin->getTimeout())
            ->onQueue($plugin->getQueue())
            ->afterResponse();

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
}
