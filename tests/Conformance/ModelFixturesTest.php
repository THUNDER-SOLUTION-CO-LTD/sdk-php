<?php

declare(strict_types=1);

namespace Thunder\Tests\Conformance;

use PHPUnit\Framework\TestCase;

final class ModelFixturesTest extends TestCase
{
    public function testEverySuccessfulResponseRoundTripsWithoutInventingFields(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../fixtures'));
        $count = 0;
        foreach ($files as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }
            $fixture = json_decode(file_get_contents($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
            if (!isset($fixture['expect']['model'], $fixture['response']['body']['data'])) {
                continue;
            }
            $class = 'Thunder\\Model\\' . $fixture['expect']['model'];
            self::assertTrue(class_exists($class), $file->getPathname() . ' ' . $class);
            $data = $fixture['response']['body']['data'];
            $model = new $class($data);
            self::assertSame($data, $model->jsonSerialize(), $fixture['id']);
            ++$count;
        }
        self::assertGreaterThan(15, $count);
    }
}
