<?php

declare(strict_types=1);

namespace Thunder\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Thunder\Input\FileInput;

final class FileInputTest extends TestCase
{
    public function testSniffsBytesAndPreservesCallerStream(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aU1cAAAAASUVORK5CYII=', true);
        self::assertIsString($png);
        $file = FileInput::fromBytes($png, 'slip.png', 1024);
        self::assertSame('image/png', $file->mimeType);
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, $png);
        rewind($stream);
        self::assertSame($png, FileInput::fromStream($stream, 'slip.png', 1024)->bytes);
        self::assertSame(0, ftell($stream));
        fclose($stream);
    }

    public function testRejectsOversizeBeforeUpload(): void
    {
        $this->expectException(\Thunder\Exception\InputError::class);
        FileInput::fromBytes(str_repeat('x', 11), 'slip.png', 10);
    }

    public function testRejectsHeaderInjectionInFilename(): void
    {
        self::assertSame('slip.gif', FileInput::fromBytes('GIF89a', "slip\r\nInjected: true", 10)->filename);
    }
}
