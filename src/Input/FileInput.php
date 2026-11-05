<?php

declare(strict_types=1);

namespace Thunder\Input;

use Psr\Http\Message\StreamInterface;
use Thunder\Exception\InputError;

/** Validated upload bytes. File limits are supplied by the operation contract. */
final readonly class FileInput
{
    private function __construct(public string $bytes, public string $filename, public string $mimeType) {}

    /** Sniff an image and reject oversized or unsupported data before transport. */
    public static function fromBytes(string $bytes, string $filename = 'slip', int $maxBytes = 4194304): self
    {
        if ($bytes === '') {
            throw new InputError('EMPTY_FILE', 'Upload is empty');
        }
        if ($maxBytes < 1 || strlen($bytes) > $maxBytes) {
            throw new InputError('FILE_TOO_LARGE', 'Upload exceeds the size limit');
        }
        [$mime, $ext] = match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => ['image/jpeg', 'jpg'],
            str_starts_with($bytes, "\x89PNG") => ['image/png', 'png'],
            str_starts_with($bytes, 'GIF8') => ['image/gif', 'gif'],
            str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' => ['image/webp', 'webp'],
            default => throw new InputError('UNSUPPORTED_FILE_TYPE', 'Expected JPEG, PNG, GIF or WebP image bytes'),
        };
        return new self($bytes, 'slip.' . $ext, $mime);
    }

    /** Read a caller-owned PSR stream without closing it. */
    public static function fromPsrStream(StreamInterface $stream, int $maxBytes = 4194304): self
    {
        if ($maxBytes < 1 || $maxBytes === PHP_INT_MAX) {
            throw new InputError('INVALID_ARGUMENT', 'Invalid upload limit');
        }
        $position = $stream->isSeekable() ? $stream->tell() : null;
        try {
            $bytes = '';
            while (!$stream->eof() && strlen($bytes) <= $maxBytes) {
                $chunk = $stream->read(min(8192, $maxBytes + 1 - strlen($bytes)));
                if ($chunk === '') {
                    break;
                }
                $bytes .= $chunk;
            }
            return self::fromBytes($bytes, 'slip', $maxBytes);
        } finally {
            if ($position !== null) {
                $stream->seek($position);
            }
        }
    }

    /** Read a local regular file without permitting URL stream wrappers. */
    public static function fromPath(string $path, int $maxBytes = 4194304): self
    {
        if (!is_file($path) || !is_readable($path) || str_contains($path, '://')) {
            throw new InputError('INVALID_ARGUMENT', 'File must be a readable local regular file');
        }
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new InputError('INVALID_ARGUMENT', 'Could not open file');
        }
        try {
            return self::fromStream($stream, basename($path), $maxBytes);
        } finally {
            fclose($stream);
        }
    }

    /** Read at most limit + 1 bytes; restore the cursor of a seekable caller-owned stream.
     * @param resource $stream
     */
    public static function fromStream($stream, string $filename = 'slip', int $maxBytes = 4194304): self
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream' || $maxBytes < 1 || $maxBytes === PHP_INT_MAX) {
            throw new InputError('INVALID_ARGUMENT', 'Expected a readable stream and positive bounded size limit');
        }
        $position = ftell($stream);
        try {
            $bytes = stream_get_contents($stream, $maxBytes + 1);
            if ($bytes === false) {
                throw new InputError('INVALID_ARGUMENT', 'Could not read upload stream');
            }
            return self::fromBytes($bytes, $filename, $maxBytes);
        } finally {
            if ($position !== false && stream_get_meta_data($stream)['seekable']) {
                fseek($stream, $position);
            }
        }
    }
}
