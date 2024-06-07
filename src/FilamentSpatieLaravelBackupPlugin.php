<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup;

use Filament\Contracts\Plugin;
use Filament\Panel;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups;

class FilamentSpatieLaravelBackupPlugin implements Plugin
{
    protected string $page = Backups::class;

    protected ?string $queue = null;

    protected string $interval = '4s';

    protected bool $hasStatusListRecordsTable = true;

    protected ?int $timeout = null;

    public function register(Panel $panel): void
    {
        $panel->pages([$this->getPage()]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function getId(): string
    {
        return 'filament-spatie-backup';
    }

    public static function make(): static
    {
        return new static();
    }

    public function usingPage(string $page): static
    {
        $this->page = $page;

        return $this;
    }

    public function getPage(): string
    {
        return $this->page;
    }

    public function usingQueue(string $queue): static
    {
        $this->queue = $queue;

        return $this;
    }

    public function getQueue(): ?string
    {
        return $this->queue;
    }

    public function usingPolingInterval(string $interval): static
    {
        $this->interval = $interval;

        return $this;
    }

    public function getPolingInterval(): string
    {
        return $this->interval;
    }

    /**
     * Set the timeout (in seconds) used for the backup job. If set to 0, the job will never timeout.
     *
     * @see https://www.php.net/manual/en/function.set-time-limit.php
     */
    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Make it so that the backup job will never timeout.
     *
     * @see https://www.php.net/manual/en/function.set-time-limit.php
     */
    public function noTimeout(): static
    {
        return $this->timeout(0);
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function statusListRecordsTable(bool $condition = true): static
    {
        $this->hasStatusListRecordsTable = $condition;

        return $this;
    }

    public function hasStatusListRecordsTable(): bool
    {
        return $this->hasStatusListRecordsTable;
    }
}
