<?php

declare(strict_types=1);

namespace Thunder\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Thunder\Transport\Query;

final class QueryTest extends TestCase
{
    public function testBooleanQueryUsesLiteralWords(): void
    {
        self::assertSame('enabled=true&duplicate=false&limit=0&name=a%20b', Query::encode([
            'enabled' => true, 'duplicate' => false, 'limit' => 0, 'name' => 'a b', 'missing' => null,
        ]));
    }
}
