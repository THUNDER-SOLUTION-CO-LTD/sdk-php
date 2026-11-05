<?php

declare(strict_types=1);

namespace Thunder\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Thunder\Client;
use Thunder\Enum\AccountType;
use Thunder\Enum\QrType;
use Thunder\Exception\ConflictError;
use Thunder\Exception\DuplicateSlipError;
use Thunder\Exception\InputError;
use Thunder\Exception\InvalidImageError;
use Thunder\Exception\InvalidRequestError;
use Thunder\Exception\InvalidUrlError;
use Thunder\Exception\NotFoundError;
use Thunder\Input\AsyncVerifyOptions;
use Thunder\Input\BatchOptions;
use Thunder\Input\BatchSlip;
use Thunder\Input\CreateBankAccountParams;
use Thunder\Input\FileInput;
use Thunder\Input\ListBankAccountsParams;
use Thunder\Input\QrRequest;
use Thunder\Input\UpdateBankAccountParams;
use Thunder\Input\V1VerifyOptions;
use Thunder\Input\VerifyOptions;
use Thunder\Input\WaitOptions;
use Thunder\Model\BankSlip;

/**
 * Integration tests against the live Thunder API. Run them with `composer test:integration`.
 *
 * Every test is skipped unless an API key is set (the key is never printed):
 *   THUNDER_API_KEY or THUNDER_API_KEY_FILE
 * Optional:
 *   THUNDER_V1_BASE_URL, THUNDER_V2_BASE_URL  another API host, such as a test environment
 *   THUNDER_TEST_WRITE=1        also creates, updates, links and unlinks a test bank account. The
 *                                account stays on the service, because the API cannot delete it.
 *   THUNDER_TEST_PAYLOADS_FILE  two or more QR payloads, one per line, of slips this branch has
 *                                already verified, so checkDuplicate calls use no quota
 *   THUNDER_TEST_QR_DIR         qr-0.png and qr-1.png, QR images of the first two payloads
 *   THUNDER_TEST_CALLBACK_URL   an https URL that accepts webhook posts, for the async job tests
 */
final class LiveTest extends TestCase
{
    private const PRIVATE_URL = 'http://10.0.0.1/slip.png';

    /** @var list<array{string, string, string}> */
    private static array $results = [];

    private Client $client;

    public static function setUpBeforeClass(): void
    {
        if (!self::hasKey()) {
            self::record('every test', 'skip', 'THUNDER_API_KEY or THUNDER_API_KEY_FILE not set');
            return;
        }
        if (getenv('THUNDER_TEST_WRITE') !== '1') {
            self::record('bank account writes', 'skip', 'THUNDER_TEST_WRITE not set');
        }
        if (self::payloads() === []) {
            self::record('verify sample slips', 'skip', 'THUNDER_TEST_PAYLOADS_FILE or THUNDER_TEST_QR_DIR not set');
        }
        if (self::callbackUrl() === '') {
            self::record('async jobs', 'skip', 'THUNDER_TEST_CALLBACK_URL not set');
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$results === []) {
            return;
        }
        $width = max(array_map(static fn(array $row): int => strlen($row[0]), self::$results));
        $lines = ["\nintegration results"];
        foreach (self::$results as [$endpoint, $result, $note]) {
            $lines[] = rtrim(str_pad($endpoint, $width) . '  ' . $result . '  ' . $note);
        }
        // Written to stderr, so PHPUnit does not count it as test output.
        fwrite(STDERR, implode("\n", $lines) . "\n");
    }

    protected function setUp(): void
    {
        if (!self::hasKey()) {
            self::markTestSkipped('THUNDER_API_KEY or THUNDER_API_KEY_FILE not set');
        }
        $this->client = new Client();
    }

    public function testInfo(): void
    {
        $info = $this->client->getInfo();
        self::assertNotSame('', $info->application->name);
        self::assertNotNull($info->meta->requestId);
        self::record('GET /info', 'pass');
    }

    public function testBanks(): void
    {
        $banks = $this->client->banks->list();
        self::assertGreaterThanOrEqual(18, count($banks));
        $kbank = null;
        foreach ($banks as $bank) {
            if ($bank->code === '004') {
                $kbank = $bank;
            }
        }
        self::assertSame('KBANK', $kbank?->shortCode);
        self::record('GET /banks', 'pass', count($banks) . ' entries');
    }

    public function testRefusedUrls(): void
    {
        $this->expectError(InvalidUrlError::class, fn() => $this->client->verify->bankByUrl(self::PRIVATE_URL));
        // The SDK refuses a non-HTTP URL before sending it.
        $this->expectError(InputError::class, fn() => $this->client->verify->bankByUrl('ftp://example.com/slip.png'));
        self::record('POST /verify/bank (url)', 'pass', 'error path only: private IP');
        $this->expectError(InvalidUrlError::class, fn() => $this->client->verify->truewalletByUrl(self::PRIVATE_URL));
        self::record('POST /verify/truewallet (url)', 'pass', 'error path only: private IP');
        $this->expectError(InvalidUrlError::class, fn() => $this->client->v1->verifyBankByUrl(self::PRIVATE_URL));
        self::record('v1 POST /verify (url)', 'pass', 'error path only: private IP');
    }

    public function testTrueMoneyWalletRefusesABlankImage(): void
    {
        $blank = FileInput::fromPath(__DIR__ . '/../fixtures/files/blank.png');
        $base64 = base64_encode($blank->bytes);
        $image = $this->expectError(InvalidImageError::class, fn() => $this->client->verify->truewalletByImage($blank));
        $this->expectError(InvalidImageError::class, fn() => $this->client->verify->truewalletByBase64($base64));
        $v1 = $this->expectError(InvalidImageError::class, fn() => $this->client->v1->verifyTruemoneyByImage($blank));
        self::record('POST /verify/truewallet (image)', 'pass', 'error path: ' . $image->apiCode);
        self::record('POST /verify/truewallet (base64)', 'pass', 'error path: ' . $image->apiCode);
        self::record('v1 POST /verify/truewallet', 'pass', 'error path: ' . $v1->apiCode);
    }

    public function testV1MeAndEveryQrType(): void
    {
        $me = $this->client->v1->getMe();
        self::assertGreaterThanOrEqual(0, $me->usedQuota);
        self::record('v1 GET /me', 'pass');
        $requests = [
            new QrRequest(type: QrType::PROMPTPAY, msisdn: '0812345678', amount: 100.5),
            new QrRequest(type: QrType::PROMPTPAY, natId: '1234567890121'),
            new QrRequest(type: QrType::PROMPTPAY, billerId: '010555123456701', ref1: 'INV001', ref2: 'C001', amount: 125.5),
            new QrRequest(type: QrType::BILL_PAYMENT, billerId: '010555123456701', ref1: 'INV001'),
            new QrRequest(type: QrType::BILL_PAYMENT, billerId: '010555123456701', amount: 125.5),
            new QrRequest(type: QrType::KSHOP, ref1: 'KB000001234567', amount: 250),
            new QrRequest(type: QrType::MAE_MANEE, ref1: '014000123456789'),
            new QrRequest(type: QrType::TUNGNGERN, ref1: 'INV12345', merchantName: 'MY STORE', amount: 750),
        ];
        foreach ($requests as $request) {
            $code = $this->client->v1->generateQr($request);
            self::assertSame("\x89PNG", substr($code->imageBytes(), 0, 4));
            self::assertStringStartsWith('0002', $code->payload);
        }
        $this->expectError(InvalidRequestError::class, fn() => $this->client->v1->generateQr(new QrRequest(type: QrType::KSHOP)));
        self::record('v1 POST /qr/generate', 'pass', count($requests) . ' QR kinds + invalid_request');
    }

    public function testV2VerifyBankSamples(): void
    {
        [$p0] = $this->samples();
        $byPayload = $this->client->verify->bankByPayload($p0, new VerifyOptions(remark: 'sdk-php integration', checkDuplicate: true));
        self::assertTrue($byPayload->isDuplicate);
        self::assertSame('sdk-php integration', $byPayload->remark);
        self::assertFalse($byPayload->has('matchedAccount'));
        self::record('POST /verify/bank (payload)', 'pass', 'cached slip, isDuplicate true');
        $byImage = $this->client->verify->bankByImage(FileInput::fromPath(self::qr(0)), new VerifyOptions(checkDuplicate: true));
        self::assertSame($byPayload->rawSlip->transRef, $byImage->rawSlip->transRef);
        self::record('POST /verify/bank (image)', 'pass');
        $base64 = base64_encode((string) file_get_contents(self::qr(1)));
        $byBase64 = $this->client->verify->bankByBase64('data:image/png;base64,' . $base64, new VerifyOptions(checkDuplicate: true));
        self::assertNotSame('', $byBase64->rawSlip->payload);
        self::record('POST /verify/bank (base64)', 'pass');
    }

    public function testV1VerifyBankSamples(): void
    {
        [$p0] = $this->samples();
        $slip = $this->client->v1->verifyBankByPayload($p0);
        self::assertNotSame('', $slip->transRef);
        $duplicate = $this->expectError(DuplicateSlipError::class, fn() => $this->client->v1->verifyBankByPayload($p0, new V1VerifyOptions(checkDuplicate: true)));
        self::assertInstanceOf(BankSlip::class, $duplicate->slip);
        self::assertSame($slip->transRef, $duplicate->slip->transRef);
        self::record('v1 GET /verify (payload)', 'pass', 'plus duplicate_slip with typed slip');
        $byImage = $this->client->v1->verifyBankByImage(FileInput::fromPath(self::qr(0)), new V1VerifyOptions(checkDuplicate: false));
        self::assertSame($slip->transRef, $byImage->transRef);
        self::record('v1 POST /verify (image)', 'pass');
        $byBase64 = $this->client->v1->verifyBankByBase64(base64_encode((string) file_get_contents(self::qr(1))));
        self::assertNotSame('', $byBase64->transRef);
        self::record('v1 POST /verify (base64)', 'pass');
    }

    public function testBankAccounts(): void
    {
        if (getenv('THUNDER_TEST_WRITE') !== '1') {
            self::markTestSkipped('THUNDER_TEST_WRITE not set');
        }
        $params = new CreateBankAccountParams(
            bankCode: '073',
            bankNumber: '0739990001',
            nameTh: 'บัญชีทดสอบ เอสดีเค',
            nameEn: 'SDK SMOKE',
            type: AccountType::JURISTIC,
        );
        try {
            $id = $this->client->bankAccounts->create($params)->id;
            self::record('POST /bank-accounts', 'pass', 'created id ' . $id);
        } catch (ConflictError) {
            // The account is kept between runs (there is no delete endpoint), so a rerun gets 409.
            $id = null;
            foreach ($this->client->bankAccounts->iterateAll(new ListBankAccountsParams(bankCode: '073')) as $account) {
                if ($account->bankNumber === $params->bankNumber) {
                    $id = $account->id;
                }
            }
            self::assertNotNull($id);
            self::record('POST /bank-accounts', 'pass', '409 BANK_ACCOUNT_DUPLICATE, reusing id ' . $id);
        }
        self::assertTrue($this->client->bankAccounts->link($id)->linked);
        self::assertTrue($this->client->bankAccounts->link($id)->linked);
        self::record('POST /bank-accounts/{id}/link', 'pass', 'twice, idempotent');

        $account = $this->client->bankAccounts->get($id);
        self::assertSame($params->bankNumber, $account->bankNumber);
        self::record('GET /bank-accounts/{id}', 'pass');

        $updated = $this->client->bankAccounts->update($id, new UpdateBankAccountParams(nameEn: 'SDK PHP SMOKE UPDATED', extraVerify: null));
        self::assertSame('SDK PHP SMOKE UPDATED', $updated->nameEn);
        // Put the name back, so the account is left as it was found.
        $this->client->bankAccounts->update($id, new UpdateBankAccountParams(nameEn: $account->nameEn));
        self::record('PATCH /bank-accounts/{id}', 'pass');

        $page = $this->client->bankAccounts->list(new ListBankAccountsParams(bankCode: '073', limit: 100));
        self::assertContains($id, array_map(static fn($a): int => $a->id, $page->items));
        self::record('GET /bank-accounts', 'pass');
        $all = $this->client->bankAccounts->listAll(new ListBankAccountsParams(bankCode: '073', limit: 1));
        self::assertSame(1, $all->limit);
        self::record('GET /bank-accounts/all', 'pass');

        $walked = [];
        foreach ($this->client->bankAccounts->iterate(new ListBankAccountsParams(limit: 1)) as $a) {
            $walked[] = $a->id;
        }
        self::assertContains($id, $walked);
        $walkedAll = [];
        foreach ($this->client->bankAccounts->iterateAll(new ListBankAccountsParams(bankCode: '073', limit: 2)) as $a) {
            $walkedAll[] = $a->id;
        }
        self::assertContains($id, $walkedAll);
        self::record('iterate and iterateAll helpers', 'pass', 'page size 1 and 2');

        self::assertTrue($this->client->bankAccounts->unlink($id)->unlinked);
        $this->expectError(NotFoundError::class, fn() => $this->client->bankAccounts->get($id));
        self::record('DELETE /bank-accounts/{id}/link', 'pass', 'left unlinked');
    }

    public function testAsyncJobs(): void
    {
        [$p0, $p1] = $this->samples();
        $callbackUrl = self::callbackUrl();
        if ($callbackUrl === '') {
            self::markTestSkipped('THUNDER_TEST_CALLBACK_URL not set');
        }
        $one = $this->client->jobs->submitBank($p0, new AsyncVerifyOptions(callbackUrl: $callbackUrl, checkDuplicate: true));
        self::record('POST /verify/bank/async', 'pass');
        self::assertSame($one->jobId, $this->client->jobs->get($one->jobId)->jobId);
        self::record('GET /verify/bank/jobs/{jobId}', 'pass');
        $finished = $this->client->jobs->waitForJob($one->jobId, new WaitOptions(interval: 1.0, timeout: 120.0));
        self::assertContains($finished->status, ['done', 'failed']);
        self::record('waitForJob helper', 'pass', 'status ' . $finished->status);
        $batch = $this->client->jobs->submitBankBatch([new BatchSlip($p0), new BatchSlip($p1, remark: 'b')], new BatchOptions(callbackUrl: $callbackUrl));
        self::assertSame([0, 1], array_map(static fn($job): int => $job->index, $batch->jobs));
        self::record('POST /verify/bank/batch', 'pass', '2 slips');
        $many = $this->client->jobs->submitBankMany([new BatchSlip($p1)], new BatchOptions(callbackUrl: $callbackUrl));
        self::assertCount(1, $many);
        self::record('submitBankMany helper', 'pass', '1 chunk');
        $this->expectError(NotFoundError::class, fn() => $this->client->jobs->get('00000000-0000-4000-8000-000000000000'));
    }

    /**
     * @template T of \Throwable
     * @param class-string<T> $class
     * @return T
     */
    private function expectError(string $class, \Closure $call): \Throwable
    {
        try {
            $call();
        } catch (\Throwable $error) {
            self::assertInstanceOf($class, $error);
            return $error;
        }
        self::fail('Expected ' . $class);
    }

    /** @return array{string, string} */
    private function samples(): array
    {
        $payloads = self::payloads();
        if ($payloads === []) {
            self::markTestSkipped('THUNDER_TEST_PAYLOADS_FILE or THUNDER_TEST_QR_DIR not set');
        }
        return [$payloads[0], $payloads[1]];
    }

    private static function hasKey(): bool
    {
        return (string) getenv('THUNDER_API_KEY') !== '' || (string) getenv('THUNDER_API_KEY_FILE') !== '';
    }

    /** @return list<string> The sample payloads, or none unless the payload file and both QR images exist. */
    private static function payloads(): array
    {
        $file = (string) getenv('THUNDER_TEST_PAYLOADS_FILE');
        if ($file === '' || !is_file($file) || !is_file(self::qr(0)) || !is_file(self::qr(1))) {
            return [];
        }
        $lines = array_values(array_filter(array_map('trim', file($file) ?: []), static fn(string $line): bool => $line !== ''));
        return count($lines) >= 2 ? $lines : [];
    }

    private static function qr(int $index): string
    {
        return rtrim((string) getenv('THUNDER_TEST_QR_DIR'), '/') . '/qr-' . $index . '.png';
    }

    private static function callbackUrl(): string
    {
        return (string) getenv('THUNDER_TEST_CALLBACK_URL');
    }

    private static function record(string $endpoint, string $result, string $note = ''): void
    {
        self::$results[] = [$endpoint, $result, $note];
    }
}
