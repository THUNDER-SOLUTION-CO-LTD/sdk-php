<?php

declare(strict_types=1);

namespace Thunder\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Thunder\Exception\InputError;
use Thunder\Input\FileInput;

final class FileContractTest extends TestCase
{
    public function testGifAndCanonicalFilename(): void
    {
        $file = FileInput::fromBytes('GIF89a' . str_repeat("\0", 20));
        self::assertSame('image/gif', $file->mimeType);
        self::assertSame('slip.gif', $file->filename);
    }
    public function testEmptyTypedError(): void
    {
        try {
            FileInput::fromBytes('');
            self::fail('Expected input error');
        } catch (InputError $error) {
            self::assertSame('EMPTY_FILE', $error->inputCode);
        }
    }
}
