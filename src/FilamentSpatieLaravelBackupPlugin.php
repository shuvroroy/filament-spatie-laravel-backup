<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups;
use function __;

class FilamentSpatieLaravelBackupPlugin implements Plugin
{
    use EvaluatesClosures;

    protected bool | Closure $authorizeUsing = true;

    protected string $page = Backups::class;

    protected ?string $queue = null;

    protected string $interval = '4s';

    protected bool $hasStatusListRecordsTable = true;

    protected ?int $timeout = null;
    private Closure|string $navigationIcon;
    private string|Closure|null $navigationLabel = null;
    private Closure|string|null $navigationGroup = null;
    private Closure|int $navigationSort = 1;

    public function register(Panel $panel): void
    {
        $panel->pages([$this->getPage()]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function authorize(bool | Closure $callback = true): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    public function isAuthorized(): bool
    {
        return $this->evaluate($this->authorizeUsing) === true;
    }

    public static function get(): static
    {
        /** @var static $instance */
        $instance = filament(app(static::class)->getId());

        return $instance;
    }

    public function getId(): string
    {
        return 'filament-spatie-backup';
    }

    public static function make(): static
    {
        return new static;
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

    public function getHeading(): string
    {
        return __('filament-spatie-backup::backup.pages.backups.heading');
    }
    public function navigationGroup(string | Closure | null $navigationGroup): static
    {
        $this->navigationGroup = $navigationGroup;
        $this->navigationGroupSet = true;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        $navigationGroup = $this->evaluate($this->navigationGroup);

        if ($navigationGroup === null && $this->navigationGroupSet === false) {
            return __('filament-spatie-backup::backup.pages.navigation.group');
        }

        return $navigationGroup;
    }

    public function navigationSort(int | Closure $navigationSort): static
    {
        $this->navigationSort = $navigationSort;

        return $this;
    }

    public function getNavigationSort(): int
    {
        return $this->evaluate($this->navigationSort);
    }

    public function navigationIcon(string | Closure $navigationIcon): static
    {
        $this->navigationIcon = $navigationIcon;

        return $this;
    }

    public function getNavigationIcon(): string
    {
        return $this->evaluate($this->navigationIcon) ?? 'heroicon-o-cog';
    }

    public function navigationLabel(string | Closure | null $navigationLabel): static
    {
        $this->navigationLabel = $navigationLabel;

        return $this;
    }

    public function getNavigationLabel(): string
    {
        return $this->evaluate($this->navigationLabel) ?? __('filament-spatie-backup::backup.pages.backups.navigation.label');
    }
}
