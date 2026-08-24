<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups;

class FilamentSpatieLaravelBackupPlugin implements Plugin
{
    use EvaluatesClosures;

    protected static ?self $registeringPlugin = null;

    protected bool | Closure $authorizeUsing = true;

    protected string $page = Backups::class;

    protected ?string $queue = null;

    protected ?string $queueConnection = null;

    protected ?string $pollingInterval = '30s';

    protected int $cacheDuration = 30;

    protected ?int $backupLimit = null;

    protected bool $hasStatusListRecordsTable = true;

    protected ?int $timeout = null;

    protected ?string $clusterName = null;

    protected Closure | string | \BackedEnum $navigationIcon = 'heroicon-o-cog';

    protected string | Closure | null $navigationLabel = null;

    protected Closure | string | null $navigationGroup = null;

    protected bool $navigationGroupSet = false;

    protected Closure | int $navigationSort = 1;

    public function register(Panel $panel): void
    {
        static::$registeringPlugin = $this;

        try {
            $panel->pages([$this->getPage()]);
        } finally {
            static::$registeringPlugin = null;
        }
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
        if (static::$registeringPlugin instanceof static) {
            return static::$registeringPlugin;
        }

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

    public function usingQueueConnection(string $connection): static
    {
        $this->queueConnection = $connection;

        return $this;
    }

    public function getQueueConnection(): ?string
    {
        return $this->queueConnection;
    }

    public function usingPollingInterval(?string $interval): static
    {
        $this->pollingInterval = $interval;

        return $this;
    }

    /**
     * @deprecated Use usingPollingInterval() instead.
     */
    public function usingPolingInterval(?string $interval): static
    {
        return $this->usingPollingInterval($interval);
    }

    public function getPollingInterval(): ?string
    {
        return $this->pollingInterval;
    }

    /**
     * @deprecated Use getPollingInterval() instead.
     */
    public function getPolingInterval(): ?string
    {
        return $this->getPollingInterval();
    }

    public function cacheDuration(int $seconds): static
    {
        if ($seconds < 0) {
            throw new \InvalidArgumentException('The cache duration must be zero or greater.');
        }

        $this->cacheDuration = $seconds;

        return $this;
    }

    public function getCacheDuration(): int
    {
        return $this->cacheDuration;
    }

    public function backupLimit(?int $limit): static
    {
        if ($limit !== null && $limit < 1) {
            throw new \InvalidArgumentException('The backup limit must be at least one.');
        }

        $this->backupLimit = $limit;

        return $this;
    }

    public function getBackupLimit(): ?int
    {
        return $this->backupLimit;
    }

    /**
     * Set the timeout (in seconds) used for the backup job. If set to 0, the job will never timeout.
     *
     * @see https://www.php.net/manual/en/function.set-time-limit.php
     */
    public function timeout(int $seconds): static
    {
        if ($seconds < 0) {
            throw new \InvalidArgumentException('The timeout must be zero or greater.');
        }

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

    public function cluster(?string $cluster): static
    {
        $this->clusterName = $cluster;

        return $this;
    }

    public function getClusterName(): ?string
    {
        return $this->clusterName;
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
            return __('filament-spatie-backup::backup.pages.backups.navigation.group');
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

    public function navigationIcon(string | \BackedEnum | Closure $navigationIcon): static
    {
        $this->navigationIcon = $navigationIcon;

        return $this;
    }

    public function getNavigationIcon(): ?string
    {
        $icon = $this->evaluate($this->navigationIcon);

        return $icon instanceof \BackedEnum ? $icon->value : $icon;
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
