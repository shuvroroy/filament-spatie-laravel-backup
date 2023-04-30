<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Components;

use Filament\Tables;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use ShuvroRoy\FilamentSpatieLaravelBackup\Models\BackupDestination;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination as SpatieBackupDestination;
use Symfony\Component\HttpFoundation\Response;

class BackupDestinationListRecords extends Component implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected $queryString = [
        'tableSortColumn',
        'tableSortDirection',
        'tableSearchQuery' => ['except' => ''],
    ];

    public function render(): View
    {
        return view('filament-spatie-backup::components.backup-destination-list-records');
    }

    protected function getTableQuery(): Builder
    {
        return BackupDestination::query();
    }

    protected function getTableColumns(): array
    {
        return [
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
            Tables\Columns\BadgeColumn::make('size')
                ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.fields.size')),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Tables\Actions\Action::make('download')
                ->link()
                ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.actions.download'))
                ->action('download')
                ->visible(auth()->user()->can('download-backup')),

            Tables\Actions\Action::make('delete')
                ->link()
                ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.actions.delete'))
                ->action('delete')
                ->visible(auth()->user()->can('delete-backup'))
                ->requiresConfirmation(),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('disk')
                ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.filters.disk'))
                ->options(FilamentSpatieLaravelBackup::getFilterDisks()),
        ];
    }

    public function download(BackupDestination $record): Response
    {
        return Storage::disk($record->disk)->download($record->path);
    }

    public function delete(BackupDestination $record): void
    {
        SpatieBackupDestination::create($record->disk, config('backup.backup.name'))
            ->backups()
            ->first(function (Backup $backup) use ($record) {
                return $backup->path() === $record->path;
            })
            ->delete();
    }
}
