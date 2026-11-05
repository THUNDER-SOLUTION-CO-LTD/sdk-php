<?php

declare(strict_types=1);

namespace Thunder\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Thunder\Model\BankSlip;
use Thunder\Model\BankSlipResult;

final class ModelsTest extends TestCase
{
    public function testNestedModelsPreserveAbsentNullAndUnknownFields(): void
    {
        $raw = ['amountInSlip' => 0, 'matchedAccount' => null, 'future' => 'kept', 'rawSlip' => ['payload' => 'x', 'transRef' => 'ref', 'date' => '2026-10-29', 'countryCode' => 'TH', 'amount' => ['amount' => 0, 'local' => []], 'sender' => [], 'receiver' => []]];
        $result = new BankSlipResult($raw);
        self::assertInstanceOf(BankSlip::class, $result->rawSlip);
        self::assertTrue($result->has('matchedAccount'));
        self::assertFalse($result->has('remark'));
        self::assertNull($result->matchedAccount);
        self::assertSame(0.0, $result->amountInSlip);
        self::assertSame($raw, $result->jsonSerialize());
    }
}
