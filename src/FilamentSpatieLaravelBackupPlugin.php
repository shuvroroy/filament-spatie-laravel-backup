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

    protected ?string $runCrontab = null;

    protected ?string $cleanCrontab = null;

    protected bool $hasStatusListRecordsTable = true;

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

    public function statusListRecordsTable(bool $condition = true): static
    {
        $this->hasStatusListRecordsTable = $condition;

        return $this;
    }

    public function hasStatusListRecordsTable(): bool
    {
        return $this->hasStatusListRecordsTable;
    }

    public function setRunCrontab(string $schedule): static
    {
        $this->runCrontab = $schedule;

        return $this;
    }

    public function getRunCrontab(): ?string
    {
        return $this->runCrontab;
    }

    public function setCleanCrontab(string $schedule): static
    {
        $this->cleanCrontab = $schedule;

        return $this;
    }

    public function getCleanCrontab(): ?string
    {
        return $this->cleanCrontab;
    }
}
