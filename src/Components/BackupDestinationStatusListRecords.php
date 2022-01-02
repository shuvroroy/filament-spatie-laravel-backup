<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Components;

use Filament\Tables;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use ShuvroRoy\FilamentSpatieLaravelBackup\Models\BackupDestinationStatus;

class BackupDestinationStatusListRecords extends Component implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    public function render(): View
    {
        return view('filament-spatie-backup::components.backup-destination-status-list-records');
    }

    protected function getTableQuery(): Builder
    {
        return BackupDestinationStatus::query();
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')
                ->label(__('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.name')),
            Tables\Columns\TextColumn::make('disk')
                ->label(__('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.disk')),
            Tables\Columns\BooleanColumn::make('healthy')
                ->label(__('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.healthy')),
            Tables\Columns\TextColumn::make('amount')
                ->label(__('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.amount')),
            Tables\Columns\TextColumn::make('newest')
                ->label(__('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.newest')),
            Tables\Columns\BadgeColumn::make('usedStorage')
                ->label(__('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.used_storage')),
        ];
    }
}
