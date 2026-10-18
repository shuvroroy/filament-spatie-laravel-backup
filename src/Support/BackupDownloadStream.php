<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;

/** @internal */
final class BackupDownloadStream
{
    private const CHUNK_SIZE = 1024 * 1024;

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $path,
    ) {}

    public function __invoke(): void
    {
        $stream = $this->filesystem->readStream($this->path);

        if (! is_resource($stream)) {
            throw new RuntimeException('The backup could not be opened for download.');
        }

        try {
            $flushTopBuffer = $this->prepareOutputBuffers();

            while (! feof($stream)) {
                $chunk = fread($stream, self::CHUNK_SIZE);

                if ($chunk === false || ($chunk === '' && ! feof($stream))) {
                    throw new RuntimeException('The backup could not be read for download.');
                }

                echo $chunk;

                if ($flushTopBuffer) {
                    ob_flush();
                }

                flush();
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * Every write must reach the transport before the callback returns.
     * Only the top buffer can be explicitly flushed. All lower buffers must
     * drain on every write, including a final fragment smaller than a chunk.
     */
    private function prepareOutputBuffers(): bool
    {
        /** @var list<array{name: string, flags: int, chunk_size: int, buffer_used: int}> $buffers */
        $buffers = ob_get_status(true);

        // Discard ordinary buffers without closing HTTP transport callbacks.
        while ($buffers !== []) {
            $buffer = $buffers[array_key_last($buffers)];

            if ($buffer['name'] !== 'default output handler'
                || ($buffer['flags'] & PHP_OUTPUT_HANDLER_REMOVABLE) === 0) {
                break;
            }

            ob_end_clean();
            array_pop($buffers);
        }

        $topLevel = array_key_last($buffers);
        $flushTopBuffer = $topLevel !== null
            && ($buffers[$topLevel]['flags'] & PHP_OUTPUT_HANDLER_FLUSHABLE) !== 0;

        foreach ($buffers as $level => $buffer) {
            $drainsEveryWrite = $buffer['chunk_size'] === 1
                || ($level === $topLevel && $flushTopBuffer);
            $hasPendingOutput = $buffer['buffer_used'] > 0;
            $canClean = $level === $topLevel
                && $buffer['name'] === 'default output handler'
                && ($buffer['flags'] & PHP_OUTPUT_HANDLER_CLEANABLE) !== 0;

            if (! $drainsEveryWrite || ($hasPendingOutput && ! $canClean)) {
                throw new RuntimeException('The backup cannot be streamed while a protected output buffer is active.');
            }

            // Cleaning a custom handler can itself emit output. Only discard
            // pending bytes from an accessible ordinary buffer.
            if ($hasPendingOutput) {
                ob_clean();
            }
        }

        return $flushTopBuffer;
    }
}
