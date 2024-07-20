<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Components;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use PhpParser\Node\Stmt\Label;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use ShuvroRoy\FilamentSpatieLaravelBackup\Models\BackupDestination;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination as SpatieBackupDestination;

class BackupDestinationListRecords extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    /**
     * @var array<int|string, array<string, string>|string>
     */
    protected $queryString = [
        'tableSortColumn',
        'tableSortDirection',
        'tableSearchQuery' => ['except' => ''],
    ];

    public function render(): View
    {
        return view('filament-spatie-backup::components.backup-destination-list-records');
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
                Tables\Actions\Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-up-tray')
                    // ->visible(fn (): bool => $plugin->getdownloadable())
                    ->action(
                        fn (BackupDestination $record) =>
                        Artisan::call('backup:restore', [
                            '--backup' => $record->path,
                        ])
                    ),


                Tables\Actions\Action::make('download')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.actions.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (): bool => $plugin->getdownloadable())
                    ->action(fn (BackupDestination $record) => Storage::disk($record->disk)->download($record->path)),

                Tables\Actions\Action::make('delete')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.actions.delete'))
                    ->icon('heroicon-o-trash')
                    ->visible(fn (): bool => $plugin->getdeletable())
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
}
