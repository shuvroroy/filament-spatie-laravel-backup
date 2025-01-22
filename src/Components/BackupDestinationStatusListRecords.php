<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Components;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\Models\BackupDestinationStatus;

class BackupDestinationStatusListRecords extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function render(): View
    {
        return view('filament-spatie-backup::components.backup-destination-status-list-records');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(BackupDestinationStatus::query())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.name')),
                Tables\Columns\TextColumn::make('disk')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.disk')),
                Tables\Columns\IconColumn::make('healthy')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.healthy'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.amount')),
                Tables\Columns\TextColumn::make('newest')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.newest')),
                Tables\Columns\TextColumn::make('usedStorage')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.used_storage'))
                    ->badge(),
            ])
            ->filters([
                // ...
            ])
            ->actions([
                // ...
            ])
            ->bulkActions([
                // ...
            ]);
    }

    #[Computed]
    public function interval(): string
    {
        /** @var FilamentSpatieLaravelBackupPlugin $plugin */
        $plugin = filament()->getPlugin('filament-spatie-backup');

        return $plugin->getPolingInterval();
    }
}
