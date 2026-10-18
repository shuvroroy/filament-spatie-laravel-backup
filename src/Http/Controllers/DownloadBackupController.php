<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;
use League\Flysystem\WhitespacePathNormalizer;
use RuntimeException;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadBackupController
{
    public function __invoke(Request $request): StreamedResponse
    {
        $user = Filament::auth()->user();

        abort_unless($user !== null, 403);

        $plugin = FilamentSpatieLaravelBackupPlugin::get();
        $page = $plugin->getPage();

        abort_unless($plugin->isAuthorized() && $page::canAccess(), 403);
        Gate::forUser($user)->authorize('download-backup');

        $disk = $request->query('disk');
        $path = $request->query('path');

        abort_unless(
            is_string($disk)
                && in_array($disk, FilamentSpatieLaravelBackup::getDisks(), true)
                && is_string($path)
                && $path !== '',
            404,
        );

        $normalizer = new WhitespacePathNormalizer;

        try {
            $normalizedPath = $normalizer->normalizePath($path);
        } catch (FilesystemException) {
            abort(404);
        }

        $directory = $normalizer->normalizePath(config()->string('backup.backup.name'));

        // The signed URL binds the file selected in the table. Also enforce the
        // current destination configuration before opening a filesystem stream.
        abort_unless(
            $path === $normalizedPath
                && ($directory === '' || str_starts_with($path, $directory . '/')),
            404,
        );

        $filesystem = Storage::disk($disk);

        abort_unless($filesystem->exists($path), 404);

        return response()->streamDownload(function () use ($filesystem, $path): void {
            $stream = $filesystem->readStream($path);

            if (! is_resource($stream)) {
                throw new RuntimeException('The backup could not be opened for download.');
            }

            try {
                while (! feof($stream)) {
                    $chunk = fread($stream, 1024 * 1024);

                    if ($chunk === false || ($chunk === '' && ! feof($stream))) {
                        throw new RuntimeException('The backup could not be read for download.');
                    }

                    echo $chunk;
                }
            } finally {
                fclose($stream);
            }
        }, basename($path), [
            'Content-Type' => $filesystem->mimeType($path) ?: 'application/octet-stream',
            'Content-Length' => (string) $filesystem->size($path),
        ]);
    }
}
