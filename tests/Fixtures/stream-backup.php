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

if (in_array($mode, ['failed-read', 'stalled-read', 'protected', 'protected-nested', 'protected-hidden'], true)) {
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
    $filesystem->shouldReceive('size')->once()->with($path)->andReturn(15);
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

// These are genuine PHP buffers, without a test callback that silently drains
// them. Removing them must neither retain the backup nor leak earlier output.
if (in_array($mode, ['buffered', 'nested'], true)) {
    ob_start();
    echo 'accidental output before the backup';
}

if ($mode === 'nested') {
    ob_start();
    echo 'more accidental output';
}

if ($mode === 'bounded') {
    ob_start(null, 8192);
}

if ($mode === 'nonflushable') {
    ob_start(null, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_REMOVABLE);
}

$transportBytes = 0;
$transportModes = ['transport', 'transport-nested', 'transport-unbounded', 'transport-protected-flushable', 'transport-protected-bounded'];

if ($mode === 'protected-hidden') {
    ob_start();
    ob_start(fn (string $chunk): string => $chunk, 1);
}

if (in_array($mode, $transportModes, true)) {
    // Like Octane/Swoole, the handler sends the response through its own
    // transport. Merely echoing after removing it must not pass this test.
    $transport = function (string $chunk) use (&$transportBytes): string {
        $transportBytes += strlen($chunk);
        fwrite(STDOUT, $chunk);

        return '';
    };
    $chunkSize = in_array($mode, ['transport-unbounded', 'transport-protected-flushable'], true) ? 0 : 1;
    $flags = match ($mode) {
        'transport-protected-flushable' => PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE,
        'transport-protected-bounded' => 0,
        default => PHP_OUTPUT_HANDLER_STDFLAGS,
    };
    ob_start($transport, $chunkSize, $flags);

    if ($mode === 'transport-nested') {
        ob_start();
        echo 'accidental output above the transport';
    }
}

if (in_array($mode, ['protected', 'protected-nested'], true)) {
    ob_start(null, 0, 0);

    if ($mode === 'protected-nested') {
        ob_start();
    }
}

try {
    $response->sendContent();
} catch (StreamedResponseException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    fwrite(STDERR, is_resource($stream) ? "stream remains open\n" : "stream closed\n");
}

if (in_array($mode, $transportModes, true)) {
    fwrite(STDERR, 'transport bytes: ' . $transportBytes . "\n");
}

Mockery::close();

if ($coverage !== null && $coverageFile !== false) {
    file_put_contents($coverageFile, serialize($coverage->stop(false)));
}
