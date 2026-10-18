<?php

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\StreamedResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\Tests\TestCase;

require __DIR__ . '/../../vendor/autoload.php';

// Feed real subprocess execution back into PHPUnit's coverage report.
$coverageFile = getenv('BACKUP_DOWNLOAD_COVERAGE');
$coverage = null;

if ($coverageFile !== false) {
    $filter = new Filter;
    $filter->includeFile(__DIR__ . '/../../src/Http/Controllers/DownloadBackupController.php');
    $filter->includeFile(__DIR__ . '/../../src/Support/BackupDownloadStream.php');
    $coverage = new CodeCoverage((new Selector)->forLineCoverage($filter), $filter);
    $coverage->start('stream-backup');
}

class DownloadProcessUser extends User implements FilamentUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}

class UnreadableBackupStream
{
    /** @var resource | null */
    public $context;

    public static bool $emptyRead = false;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
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
}

if (! isset($argv) || count($argv) !== 5) {
    throw new RuntimeException('Expected the streaming mode, disk root, backup path and backup directory.');
}

$mode = $argv[1];
$root = $argv[2];
$path = $argv[3];
$directory = $argv[4];
$reportFile = getenv('BACKUP_DOWNLOAD_REPORT');
$app = (new TestCase('streamBackup'))->createApplication();

config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
config()->set('backup.backup.name', $directory);
config()->set('backup.backup.destination.disks', ['backups']);
config()->set('backup.monitor_backups', []);
config()->set('filesystems.disks.backups', ['driver' => 'local', 'root' => $root]);
Gate::define('download-backup', fn (): bool => true);

$panel = Panel::make()
    ->default()
    ->id('admin')
    ->path('admin')
    ->middleware(['web'])
    ->authMiddleware([Authenticate::class])
    ->plugin(FilamentSpatieLaravelBackupPlugin::make());
app(PanelRegistry::class)->register($panel);
Filament::setCurrentPanel($panel);

require __DIR__ . '/../../vendor/filament/filament/routes/web.php';

Route::getRoutes()->refreshNameLookups();
Filament::auth()->setUser(new DownloadProcessUser);
$stream = null;

if ($reportFile !== false || in_array($mode, ['failed-read', 'stalled-read', 'protected', 'protected-nested', 'protected-hidden', 'ordinary-dirty-protected', 'ordinary-dirty-hidden', 'transport-dirty'], true)) {
    $size = Storage::disk('backups')->size($path);
    if (in_array($mode, ['failed-read', 'stalled-read'], true)) {
        UnreadableBackupStream::$emptyRead = $mode === 'stalled-read';
        stream_wrapper_register('unreadable-backup', UnreadableBackupStream::class);
        $stream = fopen('unreadable-backup://backup.zip', 'rb');
    } else {
        $stream = Storage::disk('backups')->readStream($path);
    }

    if (! is_resource($stream)) {
        throw new RuntimeException('Could not open the subprocess backup fixture.');
    }

    $filesystem = Mockery::mock(FilesystemAdapter::class);
    $filesystem->shouldReceive('exists')->once()->with($path)->andReturnTrue();
    $filesystem->shouldReceive('mimeType')->once()->with($path)->andReturn('application/zip');
    $filesystem->shouldReceive('size')->once()->with($path)->andReturn($size);
    $filesystem->shouldReceive('readStream')->once()->with($path)->andReturn($stream);
    Storage::shouldReceive('disk')->once()->with('backups')->andReturn($filesystem);
}

$request = Request::create(URL::signedRoute('filament.admin.filament-spatie-backup.download', [
    'disk' => 'backups',
    'path' => $path,
]));
$response = $app->make(Kernel::class)->handle($request);

if ($response->getStatusCode() !== 200) {
    throw new RuntimeException('The subprocess download route did not return a successful streamed response.');
}

$transportBytes = 0;
$deliveredBytes = 0;
$deliveredHash = hash_init('sha256');
$deliver = function (string $chunk) use (&$deliveredBytes, $deliveredHash): string {
    $deliveredBytes += strlen($chunk);
    hash_update($deliveredHash, $chunk);
    fwrite(STDOUT, $chunk);

    return '';
};
$observerLevel = null;
$observerName = null;

if ($reportFile !== false) {
    // This transport observes only bytes that have reached the bottom. It
    // cannot drain a test buffer above it or hide a pending shutdown tail.
    ob_start($deliver, 1);
    $observerLevel = ob_get_level() - 1;
    $observerName = ob_get_status()['name'];
}

// Every stack is described bottom-to-top; ordinary modes retain their real
// PHP buffers, while custom handlers model passthrough and HTTP transports.
$encodedBuffers = getenv('BACKUP_DOWNLOAD_BUFFERS');
$buffers = $encodedBuffers !== false ? json_decode($encodedBuffers, true, 512, JSON_THROW_ON_ERROR) : [];

if (! is_array($buffers)) {
    throw new RuntimeException('Expected an output buffer descriptor list.');
}

foreach ($buffers as $buffer) {
    if (! is_array($buffer)
        || ! in_array($buffer['handler'] ?? null, ['ordinary', 'passthrough', 'transport'], true)
        || ! is_int($buffer['chunk_size'] ?? null)
        || ! is_int($buffer['flags'] ?? null)
        || ! is_string($buffer['pending'] ?? '')) {
        throw new RuntimeException('Invalid output buffer descriptor.');
    }

    $transport = function (string $chunk, int $phase) use (&$transportBytes, $deliver): string {
        if (($phase & PHP_OUTPUT_HANDLER_CLEAN) !== 0) {
            fwrite(STDERR, "transport handler must not be cleaned\n");
        }

        $transportBytes += strlen($chunk);

        return $deliver($chunk);
    };
    $handler = match ($buffer['handler']) {
        'ordinary' => null,
        'passthrough' => fn (string $chunk): string => $chunk,
        'transport' => $transport,
    };
    ob_start($handler, $buffer['chunk_size'], $buffer['flags']);
    echo $buffer['pending'] ?? '';
}

$error = null;

try {
    $response->sendContent();
} catch (StreamedResponseException $exception) {
    $error = $exception->getMessage();
    fwrite(STDERR, $error . "\n");
    fwrite(STDERR, is_resource($stream) ? "stream remains open\n" : "stream closed\n");
}

if ($reportFile !== false) {
    // The contract is complete delivery when sendContent returns, without
    // depending on shutdown to flush bytes from a persistent HTTP transport.
    $remainingBuffers = ob_get_status(true);
    file_put_contents($reportFile, json_encode([
        'bytes' => $deliveredBytes,
        'sha256' => hash_final(hash_copy($deliveredHash)),
        'pending' => array_sum(array_column($remainingBuffers, 'buffer_used')),
        'observer_present' => (ob_list_handlers()[$observerLevel] ?? null) === $observerName,
        'stream_closed' => get_resource_type($stream) === 'Unknown',
        'error' => $error,
    ], JSON_THROW_ON_ERROR));
}

if (in_array($mode, ['transport', 'transport-nested', 'transport-unbounded', 'transport-protected-flushable', 'transport-protected-bounded'], true)) {
    fwrite(STDERR, 'transport bytes: ' . $transportBytes . "\n");
}

Mockery::close();

if ($coverage !== null && $coverageFile !== false) {
    file_put_contents($coverageFile, serialize($coverage->stop(false)));
}
