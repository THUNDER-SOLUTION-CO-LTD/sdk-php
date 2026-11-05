<?php

declare(strict_types=1);

namespace Thunder\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Thunder\Client;
use Thunder\Input\V1VerifyOptions;

final class OperationsTest extends TestCase
{
    public function testV1BooleanIsTextAndReadDataIsTyped(): void
    {
        $http = new class implements ClientInterface {
            /** @var list<RequestInterface> */
            public array $requests = [];
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->requests[] = $request;
                return new Response(200, ['X-Request-Id' => 'req'], '{"status":200,"data":{"payload":"0046000600000101030140225SDKFIXTURE0001000000000005102TH91043C38","transRef":"ref","date":"2026-10-26T16:51:21+07:00","countryCode":"TH","amount":{"amount":548,"local":{"amount":0,"currency":""}},"fee":0,"ref1":"","ref2":"","ref3":"","sender":{"bank":{"id":"014","name":"ธนาคารไทยพาณิชย์","short":"SCB"},"account":{"name":{"th":"นาย สมชาย ทดสอบ"},"bank":{"type":"BANKAC","account":"xxxx-xx123-4"}}},"receiver":{"bank":{"id":"002","name":"ธนาคารกรุงเทพ","short":"BBL"},"account":{"name":{"th":"บริษัท เอสดีเค ทดสอบ จำกัด"},"bank":{"type":"BANKAC","account":"XXXXX1234X"}}}}}');
            }
        };
        $client = new Client(apiKey: 'synthetic-key', v1BaseUrl: 'https://unit.test/v1', v2BaseUrl: 'https://unit.test', httpClient: $http);
        $slip = $client->v1->verifyBankByPayload('abc', new V1VerifyOptions(checkDuplicate: true));
        self::assertSame('payload=abc&checkDuplicate=true', $http->requests[0]->getUri()->getQuery());
        self::assertSame('ref', $slip->transRef);
        self::assertSame('req', $slip->meta->requestId);
    }
}
