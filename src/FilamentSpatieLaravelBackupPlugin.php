<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup;

use BackedEnum;
use Closure;
use Filament\Clusters\Cluster;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use LogicException;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups;
use UnexpectedValueException;

class FilamentSpatieLaravelBackupPlugin implements Plugin
{
    use EvaluatesClosures;

    protected static ?self $registeringPlugin = null;

    protected bool | Closure $authorizeUsing = true;

    /** @var class-string<Backups> */
    protected string $page = Backups::class;

    protected ?string $queue = null;

    protected ?string $queueConnection = null;

    protected ?string $pollingInterval = '30s';

    protected int $cacheDuration = 30;

    protected ?int $backupLimit = null;

    protected bool $hasStatusListRecordsTable = true;

    protected ?int $timeout = null;

    /** @var class-string<Cluster>|null */
    protected ?string $clusterName = null;

    protected Closure | string | BackedEnum $navigationIcon = 'heroicon-o-cog';

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

        $instance = static::make();
        $registeredPlugin = filament($instance->getId());

        if (! $registeredPlugin instanceof static) {
            throw new LogicException('The registered Filament backup plugin has an invalid type.');
        }

        return $registeredPlugin;
    }

    public function getId(): string
    {
        return 'filament-spatie-backup';
    }

    public static function make(): static
    {
        $plugin = app(static::class);

        if (! $plugin instanceof static) {
            throw new LogicException('The container did not resolve a Filament backup plugin.');
        }

        return $plugin;
    }

    /** @param class-string<Backups> $page */
    public function usingPage(string $page): static
    {
        $this->page = $page;

        return $this;
    }

    /** @return class-string<Backups> */
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

    public function getPollingInterval(): ?string
    {
        return $this->pollingInterval;
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

    /** @param class-string<Cluster>|null $cluster */
    public function cluster(?string $cluster): static
    {
        $this->clusterName = $cluster;

        return $this;
    }

    /** @return class-string<Cluster>|null */
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

        if ($navigationGroup !== null && ! is_string($navigationGroup)) {
            throw new UnexpectedValueException('The navigation group must resolve to a string or null.');
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
        $navigationSort = $this->evaluate($this->navigationSort);

        if (! is_int($navigationSort)) {
            throw new UnexpectedValueException('The navigation sort must resolve to an integer.');
        }

        return $navigationSort;
    }

    public function navigationIcon(string | BackedEnum | Closure $navigationIcon): static
    {
        $this->navigationIcon = $navigationIcon;

        return $this;
    }

    public function getNavigationIcon(): ?string
    {
        $icon = $this->evaluate($this->navigationIcon);

        if ($icon instanceof BackedEnum) {
            $icon = $icon->value;
        }

        if ($icon !== null && ! is_string($icon)) {
            throw new UnexpectedValueException('The navigation icon must resolve to a string-backed value.');
        }

        return $icon;
    }

    public function navigationLabel(string | Closure | null $navigationLabel): static
    {
        $this->navigationLabel = $navigationLabel;

        return $this;
    }

    public function getNavigationLabel(): string
    {
        $navigationLabel = $this->evaluate($this->navigationLabel);

        if ($navigationLabel === null) {
            return __('filament-spatie-backup::backup.pages.backups.navigation.label');
        }

        if (! is_string($navigationLabel)) {
            throw new UnexpectedValueException('The navigation label must resolve to a string or null.');
        }

        return $navigationLabel;
    }
}
