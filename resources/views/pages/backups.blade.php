<x-filament-panels::page>
    <div x-data="{}" x-load-css="[@js(\Filament\Support\Facades\FilamentAsset::getStyleHref('filament-spatie-backup-styles', package: 'filament-spatie-backup'))]">
        <div class="fsb-flex fsb-flex-col fsb-gap-y-8">
            @if ($this->shouldDisplayStatusListRecords())
                <div class="fsb-mb-10">
                    @livewire(ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationStatusListRecords::class)
                </div>
            @endif
            <div>
                @livewire(ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationListRecords::class)
            </div>

            <x-filament::modal id="backup-option" width="lg">
                <x-slot name="heading">
                    <h3 class="text-xl">{{ __('filament-spatie-backup::backup.pages.backups.modal.label') }}</h3>
                </x-slot>

                <x-slot name="footer">
                    <div class="fsb-flex fsb-gap-x-2 fsb-justify-between">
                        <x-filament::button wire:click="create('only-db')" color="primary" class="fsb-w-full">
                            {{ __('filament-spatie-backup::backup.pages.backups.modal.buttons.only_db') }}
                        </x-filament::button>

                        <x-filament::button wire:click="create('only-files')" color="info" class="fsb-w-full">
                            {{ __('filament-spatie-backup::backup.pages.backups.modal.buttons.only_files') }}
                        </x-filament::button>

                        <x-filament::button wire:click="create()" color="success" class="fsb-w-full">
                            {{ __('filament-spatie-backup::backup.pages.backups.modal.buttons.db_and_files') }}
                        </x-filament::button>
                    </div>
                </x-slot>
            </x-filament::modal>
        </div>
    </div>
</x-filament-panels::page>
