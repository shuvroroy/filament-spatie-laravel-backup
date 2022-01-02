<div wire:poll.{{ config('filament-spatie-laravel-backup.polling.interval') ?? '4s' }}>
	{{ $this->table }}
</div>
