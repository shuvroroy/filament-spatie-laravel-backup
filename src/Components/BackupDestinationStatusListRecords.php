<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Components;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class BackupDestinationStatusListRecords extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public function render(): View
    {
        return view('filament-spatie-backup::components.backup-destination-status-list-records');
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(
                fn () => FilamentSpatieLaravelBackup::getBackupDestinationStatusData()
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.name')),
                TextColumn::make('disk')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.disk')),
                IconColumn::make('healthy')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.healthy'))
                    ->boolean(),
                TextColumn::make('amount')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.amount')),
                TextColumn::make('newest')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.newest')),
                TextColumn::make('usedStorage')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.used_storage'))
                    ->badge(),
            ])
            ->filters([
                // ...
            ])
            ->recordActions([
                // ...
            ])
            ->toolbarActions([
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
