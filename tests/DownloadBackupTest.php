<?php

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Filament\PanelRegistry;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\StreamedResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationListRecords;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

class DownloadBackupUser extends User implements FilamentUser, HasTenants
{
    public bool $mayDownload = true;

    public bool $mayAccessPanel = true;

    public bool $mayAccessTenant = true;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->mayAccessPanel;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->mayAccessTenant;
    }

    /** @return list<Model> */
    public function getTenants(Panel $panel): array
    {
        return [(new DownloadBackupTenant)->forceFill(['id' => 42])];
    }
}

class DownloadBackupTenant extends Model
{
    public function resolveRouteBinding(mixed $value, mixed $field = null): ?static
    {
        return $value === '42' ? $this->forceFill(['id' => 42]) : null;
    }
}

class DeniedDownloadBackupsPage extends Backups
{
    public static function canAccess(): bool
    {
        return false;
    }
}

class ProtectedDownloadBackupsPage extends Backups
{
    /** @var list<class-string> */
    protected static string | array $routeMiddleware = [DenyBackupDownloadMiddleware::class];
}

class DenyBackupDownloadMiddleware
{
    public function handle(Request $request, Closure $next): never
    {
        abort(403);
    }
}

class UnreadableBackupStream
{
    /** @var resource | null */
    public $context;

    public static bool $closed = false;

    public static bool $emptyRead = false;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        static::$closed = false;

        return true;
    }

    public function stream_eof(): bool
    {
        return false;
    }

    public function stream_read(int $count): string | false
    {
        return static::$emptyRead ? '' : false;
    }

    public function stream_close(): void
    {
        static::$closed = true;
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
    config()->set('backup.backup.name', 'test-app');
    config()->set('backup.backup.destination.disks', ['backups']);
    config()->set('backup.monitor_backups', []);

    Storage::fake('backups');
    Storage::disk('backups')->put('test-app/backup.zip', 'backup contents');

    Gate::define('download-backup', fn (DownloadBackupUser $user): bool => $user->mayDownload);
});

it('streams backup contents through an authenticated HTTP download', function () {
    registerBackupDownloadPanel();
    actingAs(new DownloadBackupUser);

    get(backupDownloadUrl())
        ->assertOk()
        ->assertDownload('backup.zip')
        ->assertHeader('Content-Length', '15')
        ->assertStreamedContent('backup contents');
});

it('streams a large backup without buffering the entire file in memory', function () {
    registerBackupDownloadPanel();
    actingAs(new DownloadBackupUser);
    $size = 128 * 1024 * 1024;
    $path = 'test-app/large-backup.zip';
    $file = fopen(Storage::disk('backups')->path($path), 'wb');

    if ($file === false) {
        throw new RuntimeException('Could not create the large backup fixture.');
    }

    try {
        expect(ftruncate($file, $size))->toBeTrue();
    } finally {
        fclose($file);
    }

    memory_reset_peak_usage();
    $initialMemory = memory_get_usage(true);
    $response = get(backupDownloadUrl(['path' => $path]))
        ->assertOk()
        ->assertDownload('large-backup.zip')
        ->assertHeader('Content-Length', (string) $size);
    $bytes = 0;

    // Discard bounded chunks to simulate the HTTP client without having the
    // test response's streamedContent() helper buffer the complete download.
    ob_start(function (string $chunk) use (&$bytes): string {
        $bytes += strlen($chunk);

        return '';
    }, 8192);

    try {
        $response->baseResponse->sendContent();
    } finally {
        ob_end_clean();
    }

    expect($bytes)->toBe($size)
        ->and(memory_get_peak_usage(true) - $initialMemory)->toBeLessThan(32 * 1024 * 1024);
});

it('requires a logged in panel user', function () {
    registerBackupDownloadPanel();

    getJson(backupDownloadUrl())->assertUnauthorized();
});

it('reports a backup stream that cannot be opened', function () {
    registerBackupDownloadPanel();
    actingAs(new DownloadBackupUser);
    $filesystem = Mockery::mock(FilesystemAdapter::class);
    $filesystem->shouldReceive('exists')->once()->with('test-app/backup.zip')->andReturnTrue();
    $filesystem->shouldReceive('mimeType')->once()->with('test-app/backup.zip')->andReturnFalse();
    $filesystem->shouldReceive('size')->once()->with('test-app/backup.zip')->andReturn(15);
    $filesystem->shouldReceive('readStream')->once()->with('test-app/backup.zip')->andReturnNull();
    Storage::shouldReceive('disk')->once()->with('backups')->andReturn($filesystem);

    $response = get(backupDownloadUrl())->assertOk()->assertHeader('Content-Type', 'application/octet-stream');

    expect(fn () => $response->baseResponse->sendContent())->toThrow(
        StreamedResponseException::class,
        'The backup could not be opened for download.',
    );
});

it('closes the backup stream when reading fails or stalls', function (bool $emptyRead) {
    registerBackupDownloadPanel();
    actingAs(new DownloadBackupUser);
    UnreadableBackupStream::$emptyRead = $emptyRead;
    expect(stream_wrapper_register('unreadable-backup', UnreadableBackupStream::class))->toBeTrue();

    try {
        $stream = fopen('unreadable-backup://backup.zip', 'rb');

        if ($stream === false) {
            throw new RuntimeException('Could not open the failing stream fixture.');
        }

        $filesystem = Mockery::mock(FilesystemAdapter::class);
        $filesystem->shouldReceive('exists')->once()->with('test-app/backup.zip')->andReturnTrue();
        $filesystem->shouldReceive('mimeType')->once()->with('test-app/backup.zip')->andReturn('application/zip');
        $filesystem->shouldReceive('size')->once()->with('test-app/backup.zip')->andReturn(15);
        $filesystem->shouldReceive('readStream')->once()->with('test-app/backup.zip')->andReturn($stream);
        Storage::shouldReceive('disk')->once()->with('backups')->andReturn($filesystem);

        $response = get(backupDownloadUrl())->assertOk();

        expect(fn () => $response->baseResponse->sendContent())->toThrow(
            StreamedResponseException::class,
            'The backup could not be read for download.',
        );

        expect(UnreadableBackupStream::$closed)->toBeTrue()
            ->and(is_resource($stream))->toBeFalse();
    } finally {
        stream_wrapper_unregister('unreadable-backup');
        UnreadableBackupStream::$emptyRead = false;
    }
})->with(['failed read' => false, 'stalled read' => true]);

it('requires download permission when requesting the file directly', function () {
    registerBackupDownloadPanel();
    $user = new DownloadBackupUser;
    $user->mayDownload = false;
    actingAs($user);

    get(backupDownloadUrl())->assertForbidden();
});

it('requires access to the panel', function () {
    registerBackupDownloadPanel();
    $user = new DownloadBackupUser;
    $user->mayAccessPanel = false;
    actingAs($user);

    get(backupDownloadUrl())->assertForbidden();
});

it('enforces plugin authorization even when the user can download', function () {
    registerBackupDownloadPanel(FilamentSpatieLaravelBackupPlugin::make()->authorize(false));
    actingAs(new DownloadBackupUser);

    get(backupDownloadUrl())->assertForbidden();
});

it('enforces authorization on the configured backup page', function () {
    registerBackupDownloadPanel(FilamentSpatieLaravelBackupPlugin::make()->usingPage(DeniedDownloadBackupsPage::class));
    actingAs(new DownloadBackupUser);

    get(backupDownloadUrl())->assertForbidden();
});

it('runs middleware required by the configured backup page', function () {
    registerBackupDownloadPanel(FilamentSpatieLaravelBackupPlugin::make()->usingPage(ProtectedDownloadBackupsPage::class));
    actingAs(new DownloadBackupUser);

    get(backupDownloadUrl())->assertForbidden();
});

it('rejects unsigned and tampered download URLs', function () {
    registerBackupDownloadPanel();
    actingAs(new DownloadBackupUser);

    get(route('filament.admin.filament-spatie-backup.download', [
        'disk' => 'backups',
        'path' => 'test-app/backup.zip',
    ]))->assertForbidden();

    get(str_replace('backup.zip', 'another.zip', backupDownloadUrl()))->assertForbidden();
});

it('rejects disks that are not configured as backup destinations', function () {
    registerBackupDownloadPanel();
    actingAs(new DownloadBackupUser);
    Storage::fake('private');
    Storage::disk('private')->put('test-app/backup.zip', 'private contents');

    get(backupDownloadUrl(['disk' => 'private']))->assertNotFound();
});

it('rejects paths outside the configured backup directory or with noncanonical segments', function (string $path) {
    registerBackupDownloadPanel();
    actingAs(new DownloadBackupUser);
    Storage::disk('backups')->put('secret.zip', 'private contents');
    Storage::disk('backups')->put('other-app/backup.zip', 'other backup');
    Storage::disk('backups')->put('test-app-other/backup.zip', 'other backup');

    get(backupDownloadUrl(['path' => $path]))->assertNotFound();
})->with([
    'outside directory' => 'secret.zip',
    'other backup directory' => 'other-app/backup.zip',
    'directory prefix collision' => 'test-app-other/backup.zip',
    'parent traversal' => 'test-app/../secret.zip',
    'traversal above disk root' => '../../secret.zip',
    'control character' => "test-app/backup\0.zip",
    'nested parent traversal' => 'test-app/nested/../backup.zip',
    'current directory segment' => 'test-app/./backup.zip',
    'leading slash' => '/test-app/backup.zip',
    'duplicate separators' => 'test-app//backup.zip',
    'backslash separators' => 'test-app\\backup.zip',
    'backup directory itself' => 'test-app',
    'empty path' => '',
]);

it('rejects missing and malformed download parameters', function (array $parameters) {
    registerBackupDownloadPanel();
    actingAs(new DownloadBackupUser);

    get(URL::signedRoute('filament.admin.filament-spatie-backup.download', $parameters))
        ->assertNotFound();
})->with([
    'missing disk' => [['path' => 'test-app/backup.zip']],
    'missing path' => [['disk' => 'backups']],
    'array disk' => [['disk' => ['backups'], 'path' => 'test-app/backup.zip']],
    'array path' => [['disk' => 'backups', 'path' => ['test-app/backup.zip']]],
]);

it('returns not found for a backup removed since the table was loaded', function () {
    registerBackupDownloadPanel();
    actingAs(new DownloadBackupUser);
    $url = backupDownloadUrl();
    Storage::disk('backups')->delete('test-app/backup.zip');

    get($url)->assertNotFound();
});

it('supports custom backup filenames and nested directories', function () {
    config()->set('backup.backup.name', 'customers/test app');
    registerBackupDownloadPanel();
    actingAs(new DownloadBackupUser);
    $path = 'customers/test app/monthly/My custom backup.zip';
    Storage::disk('backups')->put($path, 'custom backup');

    get(backupDownloadUrl(['path' => $path]))
        ->assertOk()
        ->assertDownload('My custom backup.zip')
        ->assertStreamedContent('custom backup');
});

it('uses the current panel authentication guard to authorize downloads', function () {
    config()->set('auth.guards.backup-admin', ['driver' => 'session', 'provider' => 'users']);
    registerBackupDownloadPanel(guard: 'backup-admin');
    $webUser = new DownloadBackupUser;
    $webUser->mayDownload = false;
    actingAs($webUser, 'web');
    actingAs(new DownloadBackupUser, 'backup-admin');
    auth()->shouldUse('web');

    get(backupDownloadUrl())->assertOk()->assertStreamedContent('backup contents');
});

it('creates signed download links with the current tenant and runs tenant authorization', function () {
    registerBackupDownloadPanel(tenancy: true);
    $user = new DownloadBackupUser;
    actingAs($user);
    Filament::setTenant((new DownloadBackupTenant)->forceFill(['id' => 42]));

    $component = app(BackupDestinationListRecords::class);
    $table = $component->table(Table::make($component));
    $url = $table->getAction('download')?->record([
        'disk' => 'backups',
        'path' => 'test-app/backup.zip',
    ])->getUrl();

    if ($url === null) {
        throw new LogicException('The download action did not generate a URL.');
    }

    expect($url)->toContain('/admin/42/filament-spatie-backup/download');

    get($url)->assertOk()->assertStreamedContent('backup contents');

    $user->mayAccessTenant = false;

    get($url)->assertNotFound();
});

function registerBackupDownloadPanel(
    ?FilamentSpatieLaravelBackupPlugin $plugin = null,
    string $guard = 'web',
    bool $tenancy = false,
): Panel {
    $panel = Panel::make()
        ->default()
        ->id('admin')
        ->path('admin')
        ->middleware(['web'])
        ->authMiddleware([Authenticate::class])
        ->authGuard($guard)
        ->plugin($plugin ?? FilamentSpatieLaravelBackupPlugin::make());

    if ($tenancy) {
        $panel->tenant(DownloadBackupTenant::class);
    }

    app(PanelRegistry::class)->register($panel);
    Filament::setCurrentPanel($panel);

    require __DIR__ . '/../vendor/filament/filament/routes/web.php';

    Route::getRoutes()->refreshNameLookups();

    return $panel;
}

/** @param array<string, string> $parameters */
function backupDownloadUrl(array $parameters = []): string
{
    return URL::signedRoute('filament.admin.filament-spatie-backup.download', [
        'disk' => 'backups',
        'path' => 'test-app/backup.zip',
        ...$parameters,
    ]);
}
