# Thunder SDK for PHP

[![CI](https://github.com/THUNDER-SOLUTION-CO-LTD/sdk-php/actions/workflows/ci.yml/badge.svg)](https://github.com/THUNDER-SOLUTION-CO-LTD/sdk-php/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

[Thunder](https://document.thunder.in.th) checks Thai bank transfer slips and TrueMoney Wallet receipts with the bank, so you know a payment is real before you credit an order. With this SDK, your PHP application can verify a slip, confirm that it paid your account the right amount and was not used before, and receive the results of background checks through signed webhooks.

[อ่านภาษาไทย](README.th.md)

## Features

- Verify a bank transfer slip from its image, the text in its QR code, a public image URL or a base64 string.
- Confirm in the same request that the slip paid one of your bank accounts, for the amount you expect, and was not used before.
- Verify TrueMoney Wallet receipts with the same checks.
- Send up to 100 slips at a time for background checking and get each result on your webhook.
- Manage the bank accounts you receive payments to.
- Create PromptPay and bill payment QR codes for your customers to pay with.
- Handle each kind of failure with its own exception class. Automatic retries never charge you twice for a slip.
- Work with typed result objects that your IDE and PHPStan understand, over any PSR-18 HTTP client. Guzzle is the default.

## Requirements

- PHP 8.2, 8.3, 8.4 or 8.5 with the `fileinfo` extension, which most PHP builds include.
- [Composer](https://getcomposer.org).
- A Thunder API key. Sign in to the [Thunder developer portal](https://developer.thunder.in.th) and copy the API key of your application.

## Installation

```sh
composer require thunder-solution/sdk
```

This also installs Guzzle, which the SDK sends requests with. If Composer asks whether to trust the `php-http/discovery` plugin, either answer works, because the SDK does not need the plugin to find Guzzle.

## Quick start

This program checks the slip a customer sent for an order of 1,500 baht. It confirms that the slip paid one of your registered bank accounts, that the amount is right and that the slip was not used before, then prints the payment.

Save it as `verify.php` in your project folder and put the slip image next to it as `slip.jpg`:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;
use Thunder\Exception\SlipNotFoundError;
use Thunder\Input\FileInput;
use Thunder\Input\VerifyOptions;

$thunder = new Thunder(); // reads your key from THUNDER_API_KEY
$orderTotal = 1500; // baht

try {
    $result = $thunder->verify->bankByImage(FileInput::fromPath(__DIR__ . '/slip.jpg'), new VerifyOptions(
        matchAccount: true, // did the slip pay one of your bank accounts?
        matchAmount: $orderTotal, // is the amount right?
        checkDuplicate: true, // was this slip used before?
    ));
    $slip = $result->rawSlip;

    if ($result->isDuplicate) {
        echo "This slip was already used. Do not credit the order again.\n";
    } elseif (!$result->isAmountMatched) {
        echo "The slip pays {$result->amountInSlip} baht, but the order is {$orderTotal} baht.\n";
    } elseif ($result->matchedAccount === null) {
        echo "The slip does not pay any of your bank accounts.\n";
    } else {
        echo "Payment confirmed\n";
        echo "Amount:    {$slip->amount->amount} THB\n";
        echo "Date:      {$slip->date}\n";
        echo "From:      {$slip->sender->account?->name->th} ({$slip->sender->bank?->short})\n";
        echo "To:        {$slip->receiver->account?->name->th} ({$slip->receiver->bank?->short})\n";
        echo "Reference: {$slip->transRef}\n";
    }
} catch (SlipNotFoundError) {
    echo "The bank has no record of this transfer. The slip may be fake or edited.\n";
}
```

Run it with your API key:

```sh
THUNDER_API_KEY="your-api-key" php verify.php
```

For a valid slip it prints:

```text
Payment confirmed
Amount:    1500 THB
Date:      2024-01-15T14:30:00+07:00
From:      นาย ผู้โอน ทดสอบ (KBANK)
To:        บริษัท ตัวอย่าง จำกัด (SCB)
Reference: 68370160657749I376388B35
```

`matchAccount` compares the receiver with the bank accounts you registered, so add your receiving accounts first, in the developer portal or with [`bankAccounts->create`](#receiving-bank-accounts). Each verification uses one unit of your quota.

Next steps:

- [Verify slips you receive in another form](#verify-a-bank-slip), such as QR code text or an image URL.
- [See every field of the result](#the-result-object).
- [Handle errors](#error-handling) and see what the SDK retries for you.
- [Receive background results on a webhook](#webhooks).

## Usage

Each example creates its own client so that you can copy it on its own. In your application, create one client and reuse it.

### Verify a bank slip

Pick the method that matches what your customer gives you. All four return the same [result](#the-result-object) and take the same [options](#check-amount-receiving-account-and-duplicates).

| What you have | Method |
| --- | --- |
| An image file, an upload or a stream | `verify->bankByImage($file)` |
| The text in the slip's QR code, read by a QR scanner | `verify->bankByPayload($payload)` |
| A public https URL of the slip image | `verify->bankByUrl($url)` |
| The image as a base64 string, with or without a `data:` prefix | `verify->bankByBase64($base64)` |

`bankByImage` takes a `FileInput`. Create it with `FileInput::fromPath()` from a file path, `FileInput::fromBytes()` from a string of bytes, `FileInput::fromStream()` from an open stream, or `FileInput::fromPsrStream()` from a PSR-7 stream. For a form upload in plain PHP, pass `$_FILES['slip']['tmp_name']` to `fromPath()`. The image must be a JPEG, PNG, GIF or WebP file of at most 4 MB. The SDK checks the file's content, not its name.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;

$thunder = new Thunder();

$result = $thunder->verify->bankByUrl('https://example.com/slips/1001.jpg');
echo $result->rawSlip->transRef, ' ', $result->amountInSlip, "\n";
```

### Check amount, receiving account and duplicates

Turn on the checks you need with the named arguments of `VerifyOptions`. Each one adds fields to the result, and an option you leave out adds nothing. When a check fails, the API still returns the slip, and your code decides what to do.

| Option | What it checks | Result fields | When the check fails |
| --- | --- | --- | --- |
| `matchAmount: 1500` | The slip amount equals the amount you expect, in baht. | `amountInOrder`, `isAmountMatched` | `isAmountMatched` is `false`. |
| `matchAccount: true` | The receiver is one of your registered bank accounts. | `matchedAccount` | `matchedAccount` is `null`. |
| `checkDuplicate: true` | The slip was verified before. | `isDuplicate` | `isDuplicate` is `true`. |
| `remark: 'Order 1001'` | Nothing. It saves your note of 1 to 255 characters with the verification. | `remark` | It never fails. |

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;
use Thunder\Input\FileInput;
use Thunder\Input\VerifyOptions;

$thunder = new Thunder();

$result = $thunder->verify->bankByImage(FileInput::fromPath('slip.jpg'), new VerifyOptions(
    remark: 'Order 1001',
    matchAccount: true,
    matchAmount: 1500,
    checkDuplicate: true,
));
$accepted = $result->isAmountMatched === true && $result->matchedAccount !== null && $result->isDuplicate === false;
echo $accepted ? 'accept' : 'reject', ' ', $result->remark, "\n";
```

If the same branch verified the slip before, the API returns the saved result and uses no quota. If another branch verified it, the API checks it with the bank again and uses quota. `isDuplicate` is `true` in both cases.

### TrueMoney Wallet receipts

TrueMoney Wallet receipts work the same way and take the same options. Their result has a [shorter slip](#truemoney-wallet-receipt).

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;
use Thunder\Input\FileInput;
use Thunder\Input\VerifyOptions;

$thunder = new Thunder();

$result = $thunder->verify->truewalletByImage(FileInput::fromPath('wallet-receipt.png'), new VerifyOptions(
    matchAmount: 500,
    checkDuplicate: true,
));
echo $result->rawSlip->transactionId, ' ', $result->rawSlip->amount, ' ', $result->rawSlip->receiver->name, "\n";
```

`verify->truewalletByUrl()` takes an image URL and `verify->truewalletByBase64()` takes base64.

### Account info and remaining quota

`getInfo()` returns your application, branch and quota. `quota->remaining` is `null` when your plan has no limit.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;

$thunder = new Thunder();

$info = $thunder->getInfo();
$quota = $info->application->quota;
echo "{$info->application->name}, branch {$info->branch->name}\n";
echo 'Used ', $quota->used, ', remaining ', $quota->remaining ?? 'unlimited', "\n";
```

### Receiving bank accounts

These are the accounts that `matchAccount` compares the receiver with. An account belongs to your service, and a branch only matches against the accounts linked to it. `bankAccounts->create()` adds an account and links it to the branch of your API key.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;
use Thunder\Enum\AccountType;
use Thunder\Input\CreateBankAccountParams;
use Thunder\Input\ListBankAccountsParams;
use Thunder\Input\UpdateBankAccountParams;

$thunder = new Thunder();

$account = $thunder->bankAccounts->create(new CreateBankAccountParams(
    bankCode: '004', // bank codes come from $thunder->banks->list()
    bankNumber: '1234567890',
    nameTh: 'บริษัท ตัวอย่าง จำกัด',
    nameEn: 'EXAMPLE CO., LTD.',
    type: AccountType::JURISTIC,
));
$thunder->bankAccounts->update($account->id, new UpdateBankAccountParams(nameEn: 'EXAMPLE COMPANY LIMITED'));

// One page of the accounts linked to this branch
$page = $thunder->bankAccounts->list(new ListBankAccountsParams(page: 1, limit: 20));
echo "{$page->total} accounts linked to this branch\n";

// Every account in the service. The SDK fetches the pages for you.
foreach ($thunder->bankAccounts->iterateAll() as $item) {
    echo $item->id, ' ', $item->bankNumber, ' ', $item->nameEn, "\n";
}
```

| Method | What it does |
| --- | --- |
| `bankAccounts->create($params)` | Adds an account to the service and links it to this branch. |
| `bankAccounts->get($id)` | Returns one account. |
| `bankAccounts->update($id, $params)` | Changes only the fields you pass. `extraVerify: null` clears that field. |
| `bankAccounts->link($id)` | Links an account of the service to this branch. Linking twice is fine. |
| `bankAccounts->unlink($id)` | Removes the link to this branch. The account stays in the service. |
| `bankAccounts->list($params)` | Returns one page of the accounts linked to this branch. `page` starts at 1, and `limit` is 1 to 100 (default 20). |
| `bankAccounts->listAll($params)` | Returns one page of every account in the service, with the same paging. |
| `bankAccounts->iterate($params)` | Goes through every account linked to this branch, 100 per page. |
| `bankAccounts->iterateAll($params)` | Goes through every account in the service, 100 per page. |

The four list methods take a `ListBankAccountsParams` that can filter by `type` (`AccountType::NATURAL` or `AccountType::JURISTIC`) and by `bankCode`. Adding an account that already exists throws `ConflictError`.

### Verify many slips in the background

A background job checks a slip on the Thunder side and posts the result to your [webhook](#webhooks). Use jobs when you have many slips or do not want to wait for the bank. Pass an https `callbackUrl`, unless your branch already has a default webhook URL.

This example reads the QR code text of several slips from `payloads.txt`, one per line, and submits them as one batch:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;
use Thunder\Input\BatchOptions;
use Thunder\Input\BatchSlip;

$thunder = new Thunder();
$payloads = file('payloads.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

$batch = $thunder->jobs->submitBankBatch(
    array_map(static fn(string $payload): BatchSlip => new BatchSlip($payload, checkDuplicate: true), $payloads),
    new BatchOptions(callbackUrl: 'https://example.com/webhooks/thunder'),
);

// Your webhook receives every result. You can also wait for a job here:
foreach ($batch->jobs as $submitted) {
    $job = $thunder->jobs->waitForJob($submitted->jobId);
    echo $job->jobId, ' ', $job->status, ' ', $job->result?->status, ' ', $job->result?->data?->amountInSlip, "\n";
}
```

| Method | What it does |
| --- | --- |
| `jobs->submitBank($payload, $options)` | Submits one slip. `AsyncVerifyOptions` takes the same options as `VerifyOptions` plus `callbackUrl`. |
| `jobs->submitBankBatch($slips, $options)` | Submits 1 to 100 slips in one request. Each `BatchSlip` has its own options, and `BatchOptions` holds the `callbackUrl`. |
| `jobs->submitBankMany($slips, $options)` | Submits any number of slips in batches of 100, one batch after another. |
| `jobs->get($jobId)` | Returns the job, with its result once it has finished. |
| `jobs->waitForJob($jobId, $options)` | Checks the job every `interval` seconds (default 2) until it is `done` or `failed`, for at most `timeout` seconds (default 600, which is 10 minutes). Pass a `WaitOptions` to change them. |

### Generate a payment QR code

The API creates PromptPay and bill payment QR codes that your customers scan to pay you. Creating a QR code uses no quota. `amount` is in baht, from 0.01 to 9,999,999,999.99 with at most two decimals. Leave it out to let the payer type the amount.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;
use Thunder\Enum\QrType;
use Thunder\Input\QrRequest;

$thunder = new Thunder();

// PromptPay to a phone number, for 250 baht
$promptPay = $thunder->v1->generateQr(new QrRequest(QrType::PROMPTPAY, msisdn: '0812345678', amount: 250));
file_put_contents('promptpay.png', $promptPay->imageBytes());

// Bill payment to your biller ID, with your invoice number as reference 1
$bill = $thunder->v1->generateQr(new QrRequest(
    QrType::PROMPTPAY,
    billerId: '010555512345601',
    ref1: 'INV1001',
    amount: 1500,
));
file_put_contents('bill.png', $bill->imageBytes());
```

| `type` | Required fields | Optional fields |
| --- | --- | --- |
| `QrType::PROMPTPAY` | One of `msisdn` (phone number), `natId` (national ID or tax ID), `eWalletId` or `billerId` | `amount`, and `ref1` and `ref2` with `billerId` |
| `QrType::KSHOP` | `ref1`, the shop ID: `KB` followed by 12 digits | `amount` |
| `QrType::MAE_MANEE` | `ref1`, the 15-digit shop ID | `amount` |
| `QrType::TUNGNGERN` | `ref1`, the merchant code, and `merchantName`, the shop name in English | `amount` |

The older `QrType::BILL_PAYMENT` type still works. With the same `billerId` it creates the same QR code as `QrType::PROMPTPAY`.

### Legacy API v1

Use `$thunder->v1` only if your integration already uses API v1. New code should use the v2 methods above. The v1 methods return the slip itself, and with `checkDuplicate: true` a slip that was verified before throws `DuplicateSlipError`.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;
use Thunder\Exception\DuplicateSlipError;
use Thunder\Input\FileInput;
use Thunder\Input\V1VerifyOptions;

$thunder = new Thunder();

try {
    $slip = $thunder->v1->verifyBankByImage(FileInput::fromPath('slip.jpg'), new V1VerifyOptions(checkDuplicate: true));
    echo $slip->transRef, ' ', $slip->amount->amount, "\n";
} catch (DuplicateSlipError $error) {
    echo 'This slip was already used. Transfer date: ', $error->slip?->date, "\n";
}
```

API v1 also has `verifyBankByPayload()`, `verifyBankByUrl()`, `verifyBankByBase64()`, `verifyTruemoneyByImage()` and `getMe()`.

### Many slips at once

Each method waits for the API's answer before it returns, and the SDK has no asynchronous API. To check many slips without keeping a web request waiting, submit them as [background jobs](#verify-many-slips-in-the-background) and handle the results on your webhook, or verify them in your queue workers. Keep the number of requests in flight small, because the API limits how many you can send.

## The result object

A v2 verification returns your check results at the top level and the slip as the bank reports it in `rawSlip`. The [API reference](https://document.thunder.in.th/en/v2/verify/bank/) describes the full model.

### Bank slip

`verify->bankBy...()` returns a `BankSlipResult`:

| Property | Type | Meaning | Example |
| --- | --- | --- | --- |
| `amountInSlip` | `float` | The amount transferred, in baht. | `1500.0` |
| `isAmountMatched` | `?bool` | Whether the amount equals `matchAmount`. Only with `matchAmount`, otherwise `null`. | `true` |
| `amountInOrder` | `?float` | The `matchAmount` you sent. Only with `matchAmount`. | `1500.0` |
| `matchedAccount` | `?MatchedAccount` | Your registered account that the slip paid, or `null`. Only with `matchAccount`. | `bankNumber` is `'123-4-56789-0'` |
| `isDuplicate` | `?bool` | Whether the slip was verified before. Only with `checkDuplicate`. | `false` |
| `remark` | `?string` | Your `remark`. Only when you sent one. | `'Order 1001'` |
| `rawSlip->transRef` | `string` | The bank's reference for the transfer. Each transfer has its own, so you can store it to catch reuse. | `'68370160657749I376388B35'` |
| `rawSlip->date` | `string` | When the transfer happened, in ISO 8601 with the Thai time offset. | `'2024-01-15T14:30:00+07:00'` |
| `rawSlip->amount->amount` | `float` | The amount, in baht. | `1500.0` |
| `rawSlip->fee` | `?float` | The transfer fee, when the bank reports one. | `0.0` |
| `rawSlip->sender->bank` | `?SlipBank` | The sender's bank: `id` (the bank code), `name` and `short`. | `'004'`, `'กสิกรไทย'`, `'KBANK'` |
| `rawSlip->sender->account->name` | `AccountName` | The sender's name in Thai (`th`) and English (`en`), when the bank shows it. | `'นาย ผู้โอน ทดสอบ'`, `'MR. SENDER TEST'` |
| `rawSlip->sender->account->bank` | `?AccountNumber` | The sender's account number (`account`) and its `type`, partly hidden by the bank. | `'BANKAC'`, `'123-4-xxxxx-5'` |
| `rawSlip->receiver->bank` | `?SlipBank` | The receiver's bank, in the same form as the sender's. | `'014'`, `'ไทยพาณิชย์'`, `'SCB'` |
| `rawSlip->receiver->account->name` | `AccountName` | The receiver's name. | `'บริษัท ตัวอย่าง จำกัด'` |
| `rawSlip->receiver->account->bank` | `?AccountNumber` | The receiver's account number, partly hidden. | `'BANKAC'`, `'xxx-x-x5678-x'` |
| `rawSlip->receiver->account->proxy` | `?AccountNumber` | The PromptPay ID the money was sent to, when the transfer used one. | `'MSISDN'`, `'08xxxxxxxx89'` |
| `rawSlip->ref1`, `ref2`, `ref3` | `?string` | Bill payment references. They are empty for ordinary transfers. | `''` |

`rawSlip->sender->account` and `rawSlip->receiver->account` can be `null` when the bank does not report the account, so read them with `?->`, as the quick start does.

### TrueMoney Wallet receipt

`verify->truewalletBy...()` returns a `TrueWalletResult`. Its top-level properties are the same as for a bank slip, and `rawSlip` holds:

| Property | Type | Meaning | Example |
| --- | --- | --- | --- |
| `rawSlip->transactionId` | `string` | The TrueMoney transaction ID. | `'50012345678901'` |
| `rawSlip->date` | `string` | When the payment happened, in ISO 8601. | `'2026-10-26T14:30:00+07:00'` |
| `rawSlip->amount` | `float` | The amount, in baht. | `500.0` |
| `rawSlip->sender->name` | `string` | The payer's name. | `'นาย ผู้โอน ทดสอบ'` |
| `rawSlip->receiver->name` | `string` | The receiver's name. | `'นาย รับเงิน ทดสอบ'` |
| `rawSlip->receiver->phone` | `string` | The receiver's phone number, partly hidden. | `'08x-xxx-4567'` |

Every result also has a `meta` property with the HTTP `status`, the `requestId`, the `rateLimit` counters and the response `headers`. It is not part of the data, so `json_encode($result)` returns the data as the API sent it, without `meta`.

## Configuration

Pass named arguments to `new Thunder(...)`. An option you leave out comes from its environment variable, or else from its default.

| Option | Environment variable | Default | What it does |
| --- | --- | --- | --- |
| `apiKey` | `THUNDER_API_KEY` | None | Your API key. Without a key, `new Thunder()` throws `InputError`. |
| No option | `THUNDER_API_KEY_FILE` | None | The path of a file that holds your API key, such as a Docker or Kubernetes secret. The SDK uses it when there is no `apiKey` and no `THUNDER_API_KEY`. |
| `timeout` | No variable | `30.0` | How long one attempt may take, in seconds. |
| `maxRetries` | No variable | `2` | How many times the SDK retries a failed request, from 0 to 10. `0` turns retries off. |
| `httpClient` | No variable | A Guzzle client | The PSR-18 client the SDK sends requests with. |
| `requestFactory` | No variable | Found by `php-http/discovery` | The PSR-17 factory the SDK creates requests with. |
| `v2BaseUrl` | `THUNDER_V2_BASE_URL` | `https://api.thunder.in.th/v2` | The address of API v2. |
| `v1BaseUrl` | `THUNDER_V1_BASE_URL` | `https://api.thunder.in.th/v1` | The address of API v1. |
| `sleep` | No variable | `usleep()` | The function that waits between retries and between job checks. It receives the wait in seconds. Tests can pass one that returns at once. |

`Thunder` and `Client` are the same class, so `new Client(...)` works too.

### Custom HTTP client

Pass your own PSR-18 client to log requests, send them through a proxy or record them in tests. This one wraps Guzzle and logs every request:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;
use GuzzleHttp\Client as Guzzle;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class LoggingClient implements ClientInterface
{
    public function __construct(private ClientInterface $inner) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $started = microtime(true);
        $response = $this->inner->sendRequest($request);
        error_log(sprintf('%s %s %d %.0f ms', $request->getMethod(), $request->getUri(), $response->getStatusCode(), (microtime(true) - $started) * 1000));
        return $response;
    }
}

$thunder = new Thunder(httpClient: new LoggingClient(new Guzzle(['timeout' => 30, 'connect_timeout' => 10])));
echo $thunder->getInfo()->application->name, "\n";
```

When `httpClient` is a Guzzle client, the SDK applies `timeout` to each attempt and turns off redirects. With a Symfony `Psr18Client` it applies `timeout`. Any other client, like the wrapper above, must set its own timeout and must not follow redirects.

There is nothing to close. The HTTP client frees its connections when the object is destroyed, or at the latest when the PHP process ends.

## Error handling

Every exception the SDK throws is an `ThunderError`, which extends `RuntimeException`. All of them are in the `Thunder\Exception` namespace. Exceptions that come from the API are `ApiError`s, and they carry `status`, `code` (such as `SLIP_NOT_FOUND`), `getMessage()`, `requestId` and `retryAfter`. Some classes are subclasses of others: `QuotaExceededError` is a `PermissionDeniedError`; `InvalidImageError`, `InvalidUrlError` and `DuplicateSlipError` are `InvalidRequestError`s; `SlipNotFoundError` and `SlipPendingError` are `NotFoundError`s; `ServiceUnavailableError` is a `ServerError`; and `RequestTimeoutError` is a `NetworkError`.

| Exception class | HTTP status | When it happens | What to do |
| --- | --- | --- | --- |
| `AuthenticationError` | 401 | The API key is missing, wrong or revoked. | Check the key you pass or `THUNDER_API_KEY`. Create a new key in the developer portal if you need one. |
| `PermissionDeniedError` | 403 | The key is valid but may not make this request, for example because the branch is turned off, the plan has expired or your server's IP address is not on the allowed list. | Read `$error->code`, then fix that setting in the developer portal. |
| `QuotaExceededError` | 403 | Your quota is used up. This request was not charged. | Top up or change your plan, then send the request again. |
| `InvalidRequestError` | 400 | The API rejected a value in the request, such as the QR code text or an option. | Fix the value that `$error->getMessage()` names. Sending the same request again fails the same way. |
| `InvalidImageError` | 400 | The image has no readable slip QR code, is not a supported type or is too large. | Ask the customer for a clear JPEG, PNG, GIF or WebP image under 4 MB. |
| `InvalidUrlError` | 400 | The image URL or `callbackUrl` points to a private address, does not use http or https, or cannot be reached. | Use a public https URL. |
| `DuplicateSlipError` | 400 | Only in API v1 with `checkDuplicate: true`: the slip was verified before. `$error->slip` holds the first result. | Treat the payment as already received and do not credit it again. |
| `NotFoundError` | 404 | The bank account, job or other item you asked for does not exist. | Check the ID you passed. |
| `SlipNotFoundError` | 404 | The bank has no transfer that matches this slip. | Do not accept the payment. Ask the customer for the original slip. |
| `SlipPendingError` | 404 | The bank has not published the transfer yet. This happens with very recent Bangkok Bank slips. | Verify the slip again after a minute. |
| `ConflictError` | 409 | The bank account you are adding already exists. | Use the existing account. `bankAccounts->iterateAll()` finds it. |
| `RateLimitError` | 429 | You sent too many requests. `$error->retryAfter` says how many seconds to wait. | Send fewer requests at the same time. The SDK has already retried this one. |
| `ServerError` | 500, 502, 504; other 5xx except 503 | Something failed on the Thunder side. | Try again later. If it keeps happening, contact support with `$error->requestId`. |
| `ServiceUnavailableError` | 503 | The service is briefly unavailable, for example while quotas renew. | Try again in a few minutes. |
| `ApiError` | Another 4xx status | The API answered with an error status that has no class of its own. | Log `status`, `code` and `requestId`, and contact support if it continues. |
| `NetworkError` | Network | No response arrived, because of a DNS, connection or TLS failure. | Check your network or proxy, then try again. |
| `RequestTimeoutError` | Network | No response arrived within `timeout`. | Try again, or raise `timeout`. A verification may already have been counted. |
| `InputError` | Before sending | The SDK found a problem before sending, such as a missing API key, empty QR code text, an image over 4 MB or a file that is not an image. `$error->code` names the problem. | Fix the value you passed. |
| `WebhookSignatureError` | Before sending | A webhook has no signature, or its signature does not match. | Reject the webhook with status 400. |
| `JobWaitTimeoutError` | Before sending | `waitForJob()` reached its `timeout`. `$error->lastJob` is the last state it saw. | Wait longer, or rely on the webhook. |
| `BatchSubmitError` | Before sending | `submitBankMany()` stopped part way. `$error->submitted` lists the batches that were sent, `$error->failedChunk` is the index of the batch that failed, counting from 0, and `$error->cause` is its error. | Submit the failed batch and the ones after it again. Batches that were already sent keep running. |

This example checks an uploaded slip at checkout and turns each failure into a message for the customer:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;
use Thunder\Exception\ApiError;
use Thunder\Exception\InvalidImageError;
use Thunder\Exception\QuotaExceededError;
use Thunder\Exception\RateLimitError;
use Thunder\Exception\SlipNotFoundError;
use Thunder\Exception\SlipPendingError;
use Thunder\Input\FileInput;
use Thunder\Input\VerifyOptions;

function checkPayment(Thunder $thunder, string $uploadedFile, float $orderTotal): string
{
    try {
        $result = $thunder->verify->bankByImage(FileInput::fromPath($uploadedFile), new VerifyOptions(
            matchAccount: true,
            matchAmount: $orderTotal,
            checkDuplicate: true,
        ));
        if ($result->isDuplicate) {
            return 'This slip has already been used.';
        }
        if (!$result->isAmountMatched) {
            return "This slip is for {$result->amountInSlip} baht.";
        }
        if ($result->matchedAccount === null) {
            return 'This slip was not paid to our account.';
        }
        return 'Payment received.';
    } catch (SlipNotFoundError) {
        return 'We could not find this transfer at the bank.';
    } catch (SlipPendingError) {
        return 'The bank is still processing this transfer. Try again in a minute.';
    } catch (InvalidImageError) {
        return 'Please upload a clear photo or screenshot of the slip.';
    } catch (QuotaExceededError | RateLimitError $error) {
        error_log("Thunder limit reached: {$error->code} {$error->requestId}");
        return 'We cannot check slips right now. Please try again shortly.';
    } catch (ApiError $error) {
        error_log("Thunder error: {$error->status} {$error->code} {$error->requestId}");
        return 'We could not check the slip. Please try again.';
    }
}

echo checkPayment(new Thunder(), __DIR__ . '/slip.jpg', 1500), "\n";
```

### What the SDK retries

The SDK retries a failed request up to `maxRetries` times (2 by default). It waits about half a second before the first retry and doubles the wait each time, up to 8 seconds. When the API sends `Retry-After`, the SDK waits that long instead, but if the wait is longer than 60 seconds it throws the exception at once.

| Request | Retried when | Never retried when |
| --- | --- | --- |
| Reading data and linking accounts: `getInfo()`, `banks->list()`, `bankAccounts->list()`, `listAll()`, `get()` and `link()`, `jobs->get()`, `v1->getMe()`, `v1->generateQr()` | The network fails, the call times out, or the API answers 429, 500, 502, 503 or 504. | The API answers 400, 401, 403, 404 or 409. |
| Verifications and job submissions, which use quota | The API answers 429, or the connection failed before the request left your server. | The request was sent and then timed out or got a 5xx answer. |
| Changing bank accounts: `create()`, `update()` and `unlink()` | The API answers 429, or the connection failed before the request left your server. | The request was sent and then timed out or got a 5xx answer. |

A verification that reached the API may have been counted even if no answer came back. Retrying it could charge you twice, so the SDK leaves that decision to you.

## Webhooks

When a background job finishes, Thunder sends a POST request to your `callbackUrl`. If your branch has a webhook secret, the request has an `X-Thunder-Signature` header. Check the signature against the raw body before you parse or trust anything in it.

This handler is plain PHP. PHP puts the `X-Thunder-Signature` header in `$_SERVER['HTTP_X_THUNDER_SIGNATURE']`:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Exception\InputError;
use Thunder\Exception\WebhookSignatureError;
use Thunder\Webhook;

$rawBody = (string) file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_THUNDER_SIGNATURE'] ?? null;
$secret = (string) getenv('THUNDER_WEBHOOK_SECRET');

try {
    $event = Webhook::constructEvent($rawBody, is_string($signature) ? $signature : null, $secret);
} catch (WebhookSignatureError | InputError) {
    http_response_code(400);
    echo 'invalid webhook';
    exit;
}

if ($event->status === 'success') {
    error_log("Verified {$event->jobId} {$event->data?->rawSlip->transRef} {$event->data?->amountInSlip}");
} else {
    error_log("Not verified {$event->jobId} {$event->status} {$event->error?->code}");
}
echo 'ok';
```

In Laravel, pass `$request->getContent()` as the raw body and `$request->header(Webhook::SIGNATURE_HEADER)` as the signature, on a route without CSRF protection, such as one in `routes/api.php`. Do not decode the JSON and encode it again before you check it. That changes the bytes, and the signature then no longer matches.

| Method | What it does |
| --- | --- |
| `Webhook::constructEvent($rawBody, $signature, $secret)` | Checks the signature, then returns the parsed `WebhookEvent`. It throws `WebhookSignatureError` when the signature is missing or wrong, and `InputError` when the body is not a valid event. |
| `Webhook::verifySignature($rawBody, $signature, $secret)` | Returns `true` when the signature matches and `false` when it does not. |
| `Webhook::parseEvent($rawBody)` | Parses the event without checking the signature. Use it only after you have checked the signature yourself. |

`$secret` is your branch's webhook secret. Answer with a 2xx status within 5 seconds. Thunder may send the same event more than once, so use `jobId` to skip events you have already handled.

## Security and privacy

- Keep your API key on your server. Never put it in a web page, a mobile app or a public repository, because anyone who has the key can use your quota.
- Load the key from `THUNDER_API_KEY` or a secret file instead of writing it in your code.
- The SDK never puts your API key in exception messages, and `var_dump()` of a client does not show it, so you can log its errors.
- Slips show names, bank accounts and amounts, which are personal data. Store only what you need, protect it like your other customer data and follow the Thai Personal Data Protection Act (PDPA).
- Check the signature of every webhook before you act on it.

## Versioning and support policy

- Releases follow [semantic versioning](https://semver.org). Minor and patch releases do not break existing code. A breaking change comes in a new major version with upgrade notes in the changelog.
- The SDK supports the PHP versions that the PHP project still supports with security fixes. After a PHP version reaches its end of life, a later release may stop supporting it, and the changelog says so.
- [CHANGELOG.md](CHANGELOG.md) and the [releases page](https://github.com/THUNDER-SOLUTION-CO-LTD/sdk-php/releases) list every release and its changes.

## Examples and documentation

- [`examples`](examples) has one runnable program for every method. Clone this repository, run `composer install`, then run an example, for example `THUNDER_API_KEY="your-api-key" php examples/verify-bank-image.php slip.jpg`. If you leave out its arguments, an example prints how to call it.
- The [SDK guide](https://document.thunder.in.th/en/sdk/php) covers this SDK in the Thunder documentation.
- The [API reference](https://document.thunder.in.th) describes every endpoint, field and [error code](https://document.thunder.in.th/en/reference/error-codes).

## Support

For account, quota or billing questions, use the [contact page](https://developer.thunder.in.th/contact) and include the request id if available. Report bugs and suggest features in [issues](https://github.com/THUNDER-SOLUTION-CO-LTD/sdk-php/issues). Never post API keys or real slip images.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for setup, checks and pull requests. Maintainers follow [RELEASING.md](RELEASING.md).

## Security reports

Report vulnerabilities privately as described in [SECURITY.md](SECURITY.md), or use the [contact page](https://developer.thunder.in.th/contact). Never report vulnerabilities in public issues.

## License

[MIT](LICENSE). Copyright (c) 2026 Thunder Solution Co., Ltd.
