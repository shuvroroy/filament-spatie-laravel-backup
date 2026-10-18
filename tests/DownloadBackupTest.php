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
use PHPUnit\Runner\CodeCoverage;
use SebastianBergmann\CodeCoverage\Data\RawCodeCoverageData;
use ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationListRecords;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups;
use Symfony\Component\Process\Process;

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
        ->assertHeader('Content-Length', '15');

    $result = streamBackupDownloadInSubprocess();

    expect($result['bytes'])->toBe(15)
        ->and($result['sha256'])->toBe(hash('sha256', 'backup contents'))
        ->and($result['stderr'])->toBe('');
});

it('streams a backup larger than the PHP memory limit through output buffers', function (string $mode) {
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

    get(backupDownloadUrl(['path' => $path]))
        ->assertOk()
        ->assertDownload('large-backup.zip')
        ->assertHeader('Content-Length', (string) $size);
    $result = streamBackupDownloadInSubprocess($mode, $path);

    expect($result['bytes'])->toBe($size)
        ->and($result['sha256'])->toBe(hash_file('sha256', Storage::disk('backups')->path($path)))
        ->and($result['stderr'])->toBe('');
})->with(['plain', 'buffered', 'nested', 'bounded', 'nonflushable', 'ordinary-cleanable', 'ordinary-removable']);

it('cleans pending ordinary output before streaming the backup', function (string $mode) {
    $result = streamBackupDownloadInSubprocess($mode);

    expect($result['bytes'])->toBe(15)
        ->and($result['sha256'])->toBe(hash('sha256', 'backup contents'))
        ->and($result['stderr'])->toBe('');
})->with(['ordinary-cleanable', 'ordinary-removable']);

it('preserves output handlers that provide the response transport', function (string $mode) {
    $result = streamBackupDownloadInSubprocess($mode, observe: true);

    assertBackupDeliveredBeforeReturn($result, 15, hash('sha256', 'backup contents'));

    expect($result['stderr'])->toBe("transport bytes: 15\n");
})->with(['transport', 'transport-nested', 'transport-unbounded', 'transport-protected-flushable', 'transport-protected-bounded']);

it('completes or rejects an output buffer stack before returning', function (string $scenario, int $size) {
    $contract = backupOutputBufferContracts()[$scenario];
    $backup = writeOutputContractBackup($size);
    $result = streamBackupDownloadInSubprocess('contract', $backup['path'], $contract['buffers'], observe: true);

    if ($contract['allowed']) {
        assertBackupDeliveredBeforeReturn($result, $size, $backup['sha256']);

        expect($result['stderr'])->toBe('');

        return;
    }

    $error = 'The backup cannot be streamed while a protected output buffer is active.';

    expect($result['report'])->toMatchArray([
        'bytes' => 0,
        'sha256' => hash('sha256', ''),
        'pending' => 0,
        'observer_present' => true,
        'stream_closed' => true,
        'error' => $error,
    ])->and($result['bytes'])->toBe(0)
        ->and($result['stderr'])->toBe($error . "\nstream closed\n");
})->with(array_keys(backupOutputBufferContracts()))->with([15, 1024 * 1024 + 17]);

it('delivers every byte at buffer and read boundaries before returning', function (int $size) {
    $backup = writeOutputContractBackup($size);
    $result = streamBackupDownloadInSubprocess('contract', $backup['path'], [
        backupOutputBuffer('passthrough', 1, 0),
        backupOutputBuffer('passthrough', 8192),
    ], observe: true);

    assertBackupDeliveredBeforeReturn($result, $size, $backup['sha256']);

    expect($result['stderr'])->toBe('');
})->with([0, 1, 8191, 8192, 8193, 1048575, 1048576, 1048577]);

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

it('closes the backup stream when reading fails or stalls', function (string $mode) {
    $result = streamBackupDownloadInSubprocess($mode);

    expect($result['bytes'])->toBe(0)
        ->and($result['stderr'])->toBe("The backup could not be read for download.\nstream closed\n");
})->with(['failed-read', 'stalled-read']);

it('rejects protected output buffers without leaking backup bytes or leaving streams open', function (string $mode) {
    $result = streamBackupDownloadInSubprocess($mode);

    expect($result['bytes'])->toBe(0)
        ->and($result['stderr'])->toBe("The backup cannot be streamed while a protected output buffer is active.\nstream closed\n");
})->with(['protected', 'protected-nested', 'protected-hidden']);

it('rejects dirty output buffers that cannot be cleaned before streaming', function (string $mode) {
    $result = streamBackupDownloadInSubprocess($mode);
    $pendingOutput = 'accidental output before the backup';

    // PHP still flushes the pre-existing output during shutdown. The archive
    // must never be appended to a buffer that the response cannot clean.
    expect($result['bytes'])->toBe(strlen($pendingOutput))
        ->and($result['sha256'])->toBe(hash('sha256', $pendingOutput))
        ->and($result['stderr'])->toBe("The backup cannot be streamed while a protected output buffer is active.\nstream closed\n");
})->with(['ordinary-dirty-protected', 'ordinary-dirty-hidden', 'transport-dirty']);

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
        ->assertDownload('My custom backup.zip');

    $result = streamBackupDownloadInSubprocess('nested', $path);

    expect($result['bytes'])->toBe(13)
        ->and($result['sha256'])->toBe(hash('sha256', 'custom backup'))
        ->and($result['stderr'])->toBe('');
});

it('uses the current panel authentication guard to authorize downloads', function () {
    config()->set('auth.guards.backup-admin', ['driver' => 'session', 'provider' => 'users']);
    registerBackupDownloadPanel(guard: 'backup-admin');
    $webUser = new DownloadBackupUser;
    $webUser->mayDownload = false;
    actingAs($webUser, 'web');
    actingAs(new DownloadBackupUser, 'backup-admin');
    auth()->shouldUse('web');

    get(backupDownloadUrl())->assertOk()->assertDownload('backup.zip');
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

    get($url)->assertOk()->assertDownload('backup.zip');

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

/**
 * @param  list<array{handler: 'ordinary'|'passthrough'|'transport', chunk_size: int, flags: int, pending: string}>|null  $buffers
 * @return array{bytes: int, sha256: string, stderr: string, report: array<mixed>|null}
 */
function streamBackupDownloadInSubprocess(string $mode = 'plain', string $path = 'test-app/backup.zip', ?array $buffers = null, bool $observe = false): array
{
    // A real streaming response closes output buffers, including PHPUnit's
    // capture. Execute it separately and drain stdout without retaining it.
    $iniFile = php_ini_loaded_file();
    $process = new Process([
        PHP_BINARY,
        ...($iniFile !== false ? ['-c', $iniFile] : []),
        '-d', 'memory_limit=96M',
        __DIR__ . '/Fixtures/stream-backup.php',
        $mode,
        Storage::disk('backups')->path(''),
        $path,
        config()->string('backup.backup.name'),
    ]);
    $process->setTimeout(30);
    $bytes = 0;
    $hash = hash_init('sha256');
    $coverageFile = CodeCoverage::instance()->isActive() ? tempnam(sys_get_temp_dir(), 'backup-coverage-') : false;
    $reportFile = $observe ? tempnam(sys_get_temp_dir(), 'backup-report-') : false;
    $report = null;
    $process->setEnv([
        'BACKUP_DOWNLOAD_COVERAGE' => $coverageFile,
        'BACKUP_DOWNLOAD_REPORT' => $reportFile,
        'BACKUP_DOWNLOAD_BUFFERS' => json_encode($buffers ?? backupOutputBufferScenario($mode), JSON_THROW_ON_ERROR),
        'XDEBUG_MODE' => 'coverage',
    ]);

    try {
        $process->run(function (string $type, string $chunk) use ($process, &$bytes, $hash): void {
            if ($type === Process::OUT) {
                $bytes += strlen($chunk);
                hash_update($hash, $chunk);
                $process->clearOutput();
            }
        });

        expect($process->getExitCode())->toBe(0, $process->getErrorOutput());

        if ($reportFile !== false) {
            $data = file_get_contents($reportFile);
            $report = $data !== false ? json_decode($data, true, 512, JSON_THROW_ON_ERROR) : null;

            if (! is_array($report)) {
                throw new RuntimeException('The download subprocess did not return its delivery report.');
            }
        }

        if ($coverageFile !== false) {
            $data = file_get_contents($coverageFile);
            $coverage = $data !== false ? unserialize($data, ['allowed_classes' => [RawCodeCoverageData::class]]) : null;

            if (! $coverage instanceof RawCodeCoverageData) {
                throw new RuntimeException('The download subprocess did not return coverage data.');
            }

            CodeCoverage::instance()->codeCoverage()->append($coverage);
        }
    } finally {
        if ($coverageFile !== false) {
            unlink($coverageFile);
        }

        if ($reportFile !== false) {
            unlink($reportFile);
        }
    }

    return ['bytes' => $bytes, 'sha256' => hash_final($hash), 'stderr' => $process->getErrorOutput(), 'report' => $report];
}

/**
 * @param  'ordinary'|'passthrough'|'transport'  $handler
 * @return array{handler: 'ordinary'|'passthrough'|'transport', chunk_size: int, flags: int, pending: string}
 */
function backupOutputBuffer(string $handler = 'ordinary', int $chunkSize = 0, int $flags = PHP_OUTPUT_HANDLER_STDFLAGS, string $pending = ''): array
{
    return ['handler' => $handler, 'chunk_size' => $chunkSize, 'flags' => $flags, 'pending' => $pending];
}

/** @return array<string, array{buffers: list<array{handler: 'ordinary'|'passthrough'|'transport', chunk_size: int, flags: int, pending: string}>, allowed: bool}> */
function backupOutputBufferContracts(): array
{
    return [
        'flushable top with a larger threshold' => [
            'buffers' => [backupOutputBuffer('passthrough', 8192)],
            'allowed' => true,
        ],
        'nonflushable top with a larger threshold' => [
            'buffers' => [backupOutputBuffer('passthrough', 8192, PHP_OUTPUT_HANDLER_REMOVABLE)],
            'allowed' => false,
        ],
        'immediate nonflushable top' => [
            'buffers' => [backupOutputBuffer('passthrough', 1, 0)],
            'allowed' => true,
        ],
        'inaccessible lower buffer with a larger threshold' => [
            'buffers' => [backupOutputBuffer('passthrough', 8192), backupOutputBuffer('passthrough', 8192)],
            'allowed' => false,
        ],
        'immediate lower buffer beneath a flushable top' => [
            'buffers' => [backupOutputBuffer('passthrough', 1, 0), backupOutputBuffer('passthrough', 8192)],
            'allowed' => true,
        ],
        'unbounded flushable top' => [
            'buffers' => [backupOutputBuffer('passthrough')],
            'allowed' => true,
        ],
    ];
}

/** @return list<array{handler: 'ordinary'|'passthrough'|'transport', chunk_size: int, flags: int, pending: string}> */
function backupOutputBufferScenario(string $mode): array
{
    $pending = 'accidental output before the backup';

    return match ($mode) {
        'plain', 'failed-read', 'stalled-read' => [],
        'buffered' => [backupOutputBuffer(pending: $pending)],
        'nested' => [backupOutputBuffer(pending: $pending), backupOutputBuffer(pending: 'more accidental output')],
        'bounded' => [backupOutputBuffer(chunkSize: 8192)],
        'nonflushable' => [backupOutputBuffer(flags: PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_REMOVABLE)],
        'ordinary-cleanable' => [backupOutputBuffer(flags: PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE, pending: $pending)],
        'ordinary-removable' => [backupOutputBuffer(flags: PHP_OUTPUT_HANDLER_REMOVABLE, pending: $pending)],
        'ordinary-dirty-protected' => [backupOutputBuffer(flags: PHP_OUTPUT_HANDLER_FLUSHABLE, pending: $pending)],
        'ordinary-dirty-hidden' => [backupOutputBuffer(chunkSize: 8192, pending: $pending), backupOutputBuffer('passthrough', 1)],
        'transport-dirty' => [backupOutputBuffer('transport', 8192, pending: $pending)],
        'transport' => [backupOutputBuffer('transport', 1)],
        'transport-nested' => [backupOutputBuffer('transport', 1), backupOutputBuffer(pending: 'accidental output above the transport')],
        'transport-unbounded' => [backupOutputBuffer('transport')],
        'transport-protected-flushable' => [backupOutputBuffer('transport', flags: PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE)],
        'transport-protected-bounded' => [backupOutputBuffer('transport', 1, 0)],
        'protected' => [backupOutputBuffer(flags: 0)],
        'protected-nested' => [backupOutputBuffer(flags: 0), backupOutputBuffer()],
        'protected-hidden' => [backupOutputBuffer(), backupOutputBuffer('passthrough', 1)],
        default => throw new LogicException('Unknown output buffer scenario.'),
    };
}

/** @return array{path: string, sha256: string} */
function writeOutputContractBackup(int $size): array
{
    $tail = substr("distinct-tail-\0\xffXY", 0, min($size, 17));
    $prefixLength = $size - strlen($tail);
    $contents = substr(str_repeat('0123456789abcdef', intdiv($prefixLength + 15, 16)), 0, $prefixLength) . $tail;
    $path = 'test-app/contract.zip';
    Storage::disk('backups')->put($path, $contents);

    return ['path' => $path, 'sha256' => hash('sha256', $contents)];
}

/** @param array{bytes: int, sha256: string, report: array<mixed>|null} $result */
function assertBackupDeliveredBeforeReturn(array $result, int $size, string $sha256): void
{
    // Compare the transport snapshot taken when sendContent returns with the
    // complete process output, so shutdown cannot rescue a truncated response.
    expect($result['report'])->toMatchArray([
        'bytes' => $size,
        'sha256' => $sha256,
        'pending' => 0,
        'observer_present' => true,
        'stream_closed' => true,
        'error' => null,
    ])->and($result['bytes'])->toBe($size)
        ->and($result['sha256'])->toBe($sha256);
}
