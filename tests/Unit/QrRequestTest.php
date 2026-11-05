<?php

declare(strict_types=1);

namespace Thunder\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Thunder\Client;
use Thunder\Enum\QrType;
use Thunder\Input\QrRequest;

/** QR request shapes of the API v1 rules of 2026-10-30 are sent exactly as given. */
final class QrRequestTest extends TestCase
{
    public function testNewQrShapesAreSentUnchanged(): void
    {
        $cases = [
            [new QrRequest(type: QrType::PROMPTPAY, billerId: '010555123456701', amount: 125.5), '{"type":"PROMPTPAY","billerId":"010555123456701","amount":125.5}'],
            [new QrRequest(type: QrType::BILL_PAYMENT, billerId: '010555123456701'), '{"type":"BILL_PAYMENT","billerId":"010555123456701"}'],
            [new QrRequest(type: QrType::KSHOP, ref1: 'KB000001234567', amount: 250), '{"type":"KSHOP","ref1":"KB000001234567","amount":250}'],
            [new QrRequest(type: QrType::KSHOP, ref1: 'kps004kb000001234567'), '{"type":"KSHOP","ref1":"kps004kb000001234567"}'],
            [new QrRequest(type: QrType::MAE_MANEE, ref1: '014000123456789'), '{"type":"MAE_MANEE","ref1":"014000123456789"}'],
            [new QrRequest(type: QrType::TUNGNGERN, ref1: 'INV12345', merchantName: 'MY STORE', amount: 750), '{"type":"TUNGNGERN","ref1":"INV12345","merchantName":"MY STORE","amount":750}'],
        ];
        $http = new class implements ClientInterface {
            /** @var list<RequestInterface> */
            public array $requests = [];
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->requests[] = $request;
                return new Response(200, [], '{"status":200,"data":{"image":"iVBORw0KGgo=","mime":"image/png","payload":"000201"}}');
            }
        };
        $client = new Client(apiKey: 'synthetic-key', v1BaseUrl: 'https://unit.test/v1', v2BaseUrl: 'https://unit.test', httpClient: $http);
        foreach ($cases as [$request, $wire]) {
            self::assertSame('000201', $client->v1->generateQr($request)->payload);
        }
        foreach ($cases as $i => [, $wire]) {
            self::assertSame('/v1/qr/generate', $http->requests[$i]->getUri()->getPath());
            self::assertEquals(json_decode($wire, true), json_decode((string) $http->requests[$i]->getBody(), true));
        }
    }
}
