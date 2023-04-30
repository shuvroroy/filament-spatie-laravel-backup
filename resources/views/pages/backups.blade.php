<x-filament::page>
    @if(!$this->getHideBackupStatusTable())
        @livewire(ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationStatusListRecords::class)
    @endif
    <div class="!mt-8">
        @livewire(ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationListRecords::class)
    </div>

    <x-tables::modal id="backup-option" width="lg">
        <x-slot name="subheading">
            <h3 class="text-xl">{{ __('filament-spatie-backup::backup.pages.backups.modal.label') }}</h3>
        </x-slot>

        <x-slot name="actions">
            <x-filament::modal.actions full-width>
                <x-filament::button wire:click="create('only-db')" color="primary">
                    {{ __('filament-spatie-backup::backup.pages.backups.modal.buttons.only_db') }}
                </x-filament::button>

                <x-filament::button wire:click="create('only-files')" color="warning">
                    {{ __('filament-spatie-backup::backup.pages.backups.modal.buttons.only_files') }}
                </x-filament::button>

                <x-filament::button wire:click="create()" color="success">
                    {{ __('filament-spatie-backup::backup.pages.backups.modal.buttons.db_and_files') }}
                </x-filament::button>
            </x-filament::modal.actions>
        </x-slot>
    </x-tables::modal>
</x-filament::page>
