<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Components;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use RuntimeException;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class BackupDestinationListRecords extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
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
        return $table
            ->records(
                function (
                    ?string $sortColumn,
                    ?string $sortDirection,
                    ?string $search,
                    ?array $filters,
                    int | string $page,
                    int | string | null $recordsPerPage,
                ): LengthAwarePaginator {
                    $plugin = FilamentSpatieLaravelBackupPlugin::get();
                    $configuredDisks = FilamentSpatieLaravelBackup::getDisks();
                    $filteredDisk = data_get($filters, 'disk.value');
                    $filteredType = data_get($filters, 'type.value');
                    $disks = filled($filteredDisk) && in_array($filteredDisk, $configuredDisks, true)
                        ? [$filteredDisk]
                        : $configuredDisks;
                    $data = [];

                    foreach ($disks as $disk) {
                        $data = array_merge(
                            $data,
                            FilamentSpatieLaravelBackup::getBackupDestinationData(
                                $disk,
                                $plugin->getCacheDuration(),
                            ),
                        );
                    }

                    $data = collect($data)
                        ->when(
                            filled($filteredType),
                            fn (Collection $data): Collection => $data->where('type', $filteredType),
                        )
                        ->sortByDesc('date')
                        ->when(
                            $plugin->getBackupLimit() !== null,
                            fn (Collection $data): Collection => $data->take($plugin->getBackupLimit()),
                        )
                        ->when(
                            filled($sortColumn),
                            fn (Collection $data): Collection => $data->sortBy(
                                $sortColumn,
                                SORT_NATURAL,
                                $sortDirection === 'desc',
                            ),
                        )
                        ->when(
                            filled($search),
                            fn (Collection $data): Collection => $data->filter(
                                fn (array $record): bool => Str::contains(
                                    Str::lower($record['path'] . $record['disk'] . $record['date']),
                                    Str::lower($search),
                                ),
                            ),
                        )
                        ->values();

                    $page = max((int) $page, 1);
                    $recordsPerPage = $recordsPerPage === 'all'
                        ? max($data->count(), 1)
                        : max((int) ($recordsPerPage ?? 10), 1);

                    return new LengthAwarePaginator(
                        items: $data->forPage($page, $recordsPerPage),
                        total: $data->count(),
                        perPage: $recordsPerPage,
                        currentPage: $page,
                    );
                }
            )
            ->columns([
                TextColumn::make('path')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.fields.path'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('disk')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.fields.disk'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.fields.date'))
                    ->dateTime()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('size')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.fields.size'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('disk')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.filters.disk'))
                    ->options(FilamentSpatieLaravelBackup::getFilterDisks()),
                SelectFilter::make('type')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.filters.type'))
                    ->options(FilamentSpatieLaravelBackup::getFilterTypes()),
            ])
            ->deferFilters(false)
            ->paginationPageOptions([10, 25, 50])
            ->recordActions([
                Action::make('download')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.actions.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(auth()->user()?->can('download-backup') ?? false)
                    ->action(fn (array $record) => Storage::disk($record['disk'])->download($record['path'])),

                Action::make('delete')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.actions.delete'))
                    ->icon('heroicon-o-trash')
                    ->visible(auth()->user()?->can('delete-backup') ?? false)
                    ->requiresConfirmation()
                    ->color('danger')
                    ->modalIcon('heroicon-o-trash')
                    ->action(function (array $record) {
                        if (! Storage::disk($record['disk'])->delete($record['path'])) {
                            throw new RuntimeException('The backup could not be deleted.');
                        }

                        FilamentSpatieLaravelBackup::clearCachedBackupDestinationData(
                            $record['disk'],
                            config('backup.backup.name'),
                        );
                        $this->resetTable();

                        Notification::make()
                            ->title(__('filament-spatie-backup::backup.pages.backups.messages.backup_delete_success'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                // ...
            ]);
    }

    #[Computed]
    public function interval(): ?string
    {
        /** @var FilamentSpatieLaravelBackupPlugin $plugin */
        $plugin = filament()->getPlugin('filament-spatie-backup');

        return $plugin->getPollingInterval();
    }
}
