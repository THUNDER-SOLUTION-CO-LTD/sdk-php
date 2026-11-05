<?php

declare(strict_types=1);

namespace Thunder\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** The version in the User-Agent header is the one release-please last released. */
final class VersionTest extends TestCase
{
    public function testUserAgentVersionMatchesTheReleaseManifest(): void
    {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../../.release-please-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $source = (string) file_get_contents(__DIR__ . '/../../src/Transport/Transport.php');
        self::assertSame(1, preg_match("#'thunder-slip-php/([^']+)', // x-release-please-version#", $source, $match));
        self::assertSame($manifest['.'], $match[1]);
    }
}
