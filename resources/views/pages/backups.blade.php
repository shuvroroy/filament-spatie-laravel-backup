<x-filament-panels::page>
    <div class="flex flex-col gap-y-8">
        @if($this->shouldDisplayStatusListRecords())
            <div class="mb-10">
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

            <x-slot name="footer" >
                <div class="flex gap-x-2">
                    <x-filament::button wire:click="create('only-db')" color="primary" class="w-full">
                        {{ __('filament-spatie-backup::backup.pages.backups.modal.buttons.only_db') }}
                    </x-filament::button>

                    <x-filament::button wire:click="create('only-files')" color="info" class="w-full">
                        {{ __('filament-spatie-backup::backup.pages.backups.modal.buttons.only_files') }}
                    </x-filament::button>

                    <x-filament::button wire:click="create()" color="success" class="w-full">
                        {{ __('filament-spatie-backup::backup.pages.backups.modal.buttons.db_and_files') }}
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>
    </div>
</x-filament-panels::page>
